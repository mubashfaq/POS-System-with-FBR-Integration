<?php

namespace App\Http\Controllers\Backend;

use App\Models\BusinessCounter;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Godown;
use App\Models\Import;
use App\Models\ProductVariation;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Packing;
use App\Services\UomConverter;
use App\Services\StockReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Log;
use Yajra\DataTables\Facades\DataTables;

class ImportController extends Controller
{
    private function getBusinessId(): int
    {
        $businessId = session('business_id');
        if (!$businessId) {
            throw ValidationException::withMessages(['No business selected in session.',]);
        }
        return (int) $businessId;
    }

    private function generateImportNumber(int $businessId): string
    {
        $counter = BusinessCounter::lockForUpdate()->firstOrCreate(
            ['business_id' => $businessId],
            ['last_invoice_number' => 0, 'last_import_number' => 0]
        );

        $counter->increment('last_import_number');

        return 'IMP-' . $businessId . '-' . str_pad((string) $counter->last_import_number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Preview the NEXT import number for this business.
     * Does NOT consume/increment the counter.
     */
    private function peekNextImportNumber(int $businessId): string
    {
        $counter = BusinessCounter::firstOrCreate(
            ['business_id' => $businessId],
            ['last_invoice_number' => 0, 'last_import_number' => 0]
        );

        $next = (int) $counter->last_import_number + 1;

        return 'IMP-' . $businessId . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Convert empty strings to null so DB accepts them.
     */
    private function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value === '' || $value === null) {
                $data[$key] = null;
            }
        }
        return $data;
    }

    /**
     * Whitelist for the imports table.
     */
    private function allowedImportFields(): array
    {
        return [
            'container_code',
            'p_date',
            'clear_date',
            'currency_id',
            'ex_rate',
            'inv_amount',
            'inv_amount_pkr',
            'godown_id',
            'supplier_id',
            'address',
            'freight_expense',
            'fta_expense',
            'fitting_expense',
            'co_expense',
            'other_expense',
            'total_expense',
        ];
    }

    protected function createPurchaseFromImport(Import $import, array $rows, int $businessId): void
    {
        // Only accept rows that actually have a product selected.
        $clean = collect($rows)->filter(fn($r) => !empty($r['product_id']))->values();

        if ($clean->isEmpty()) {
            // If the import previously had a purchase but now has no rows, clear it
            if ($import->purchase_id) {
                $this->deleteImportPurchase($import);
            }
            return;
        }

        $supplierId = $import->supplier_id ?: null;

        // --- Build the desired line map keyed by product+variation ---
        // Structure: [ "product_id:variation_id" => [...line...] ]
        $desired = [];

        foreach ($clean as $row) {
            $product = Product::where('business_id', $businessId)->find($row['product_id']);
            if (!$product)
                continue;

            $variationId = $row['product_variation_id'] ?? null;

            if ($product->has_variations) {
                if (!$variationId) {
                    throw ValidationException::withMessages([
                        'import_products' => "Select a variation for {$product->name}.",
                    ]);
                }
                $variation = ProductVariation::where('product_id', $product->id)->where('business_id', $businessId)
                    ->findOrFail($variationId);
            } else {
                $variation = null;
            }

            $qty = round((float) ($row['total_pcs'] ?? 0), 4);

            if ($qty <= 0)
                continue;

            $purchasePrice = (float) ($row['price_pc'] ?? 0);
            $price = (float) ($product->price ?? $purchasePrice);

            $key = $product->id . ':' . ($variation?->id ?? '0');

            $desired[$key] = [
                'product' => $product,
                'variation' => $variation,
                'quantity' => $qty,
                'purchase_price' => $purchasePrice,
                'price' => $price,
            ];
        }

        if (empty($desired)) {
            if ($import->purchase_id) {
                $this->deleteImportPurchase($import);
            }
            return;
        }

        // ---- Landed cost per piece (Decision A) ----
        $categoryPool = $import->expenses()->get()
            ->groupBy('category_id')
            ->map(fn($g) => $g->sum(fn($e) => (float) $e->amount));

        $globalExpense = (float) collect(['freight_expense', 'fta_expense', 'fitting_expense', 'co_expense', 'other_expense'])
            ->sum(fn($f) => (float) $import->{$f});

        $costItems = [];
        foreach ($desired as $k => $l) {
            $costItems[$k] = [
                'category_id' => $l['product']->category_id,
                'base_qty' => (float) $l['quantity'],
                'purchase_price' => (float) $l['purchase_price'],
            ];
        }

        $calculatedCosts = $this->calculateLandedCosts($globalExpense, $categoryPool, $costItems);

        foreach ($desired as $key => &$line) {
            $line['cost_price'] = $calculatedCosts[$key]['cost_price'];
        }
        unset($line);

        // --- Load existing purchase (if any) and its items ---
        $purchase = $import->purchase_id ?
            Purchase::with(['items.product', 'items.variation'])->where('business_id', $businessId)->find($import->purchase_id)
            : null;

        // Existing items keyed the same way so we can match them 1:1.
        $existingItems = $purchase ? $purchase->items->keyBy(fn($i) => $i->product_id . ':' . ($i->product_variation_id ?? '0'))
            : collect();

        // --- First pass: validate that no line will drop below consumed qty ---
        $errors = [];

        foreach ($desired as $key => $line) {
            $existing = $existingItems->get($key);
            if (!$existing)
                continue;

            $oldQty = round((float) $existing->quantity, 4);
            $remaining = round((float) $existing->remaining_qty, 4);
            $consumed = round($oldQty - $remaining, 4);
            $newQty = round((float) $line['quantity'], 4);

            if ($newQty < $consumed) {
                $label = $line['product']->name . ($line['variation'] ? ' – ' . $line['variation']->label : '');

                $errors[] = "{$label}: imported quantity ({$newQty}) cannot be less than "
                    . "the quantity already sold ({$consumed}).";
            }
        }

        // Existing items that are being removed entirely — only allowed if nothing sold.
        foreach ($existingItems as $key => $existing) {
            if (isset($desired[$key]))
                continue;

            $consumed = round((float) $existing->quantity - (float) $existing->remaining_qty, 4);
            if ($consumed > 0) {
                $label = $existing->product->name . ($existing->variation ? ' – ' . $existing->variation->label : '');

                $errors[] = "{$label}: cannot remove this product from the import because "
                    . "{$consumed} unit(s) have already been sold.";
            }
        }

        if (!empty($errors)) {
            // ImportController::store/update is inside a DB transaction —
            // throwing here will roll everything back.
            throw ValidationException::withMessages([
                'import_products' => $errors,
            ]);
        }

        // --- Create or update the purchase header ---
        $subTotal = round(collect($desired)->sum(fn($l) => $l['quantity'] * $l['cost_price']), 2);

        $header = [
            'supplier_id' => $supplierId ? $supplierId : 0,
            'user_id' => auth()->id(),
            'business_id' => $businessId,
            'sub_total' => $subTotal,
            'tax' => 0,
            'discount_value' => 0,
            'shipping' => 0,
            'grand_total' => $subTotal,
            'date' => $import->p_date ?? now()->toDateString(),
            'status' => 1,
        ];

        if ($purchase) {
            $purchase->update($header);
        } else {
            $purchase = Purchase::create($header);

            // Link the purchase back to the import.
            $import->update(['purchase_id' => $purchase->id]);
        }

        // --- Sync items ---
        // 1. Update existing lines (adjust quantity + remaining_qty by delta).
        foreach ($desired as $key => $line) {
            $existing = $existingItems->get($key);

            if ($existing) {
                $oldQty = round((float) $existing->quantity, 4);
                $newQty = round((float) $line['quantity'], 4);
                $delta = round($newQty - $oldQty, 4);

                $existing->update([
                    'purchase_price' => $line['cost_price'],
                    'price' => $line['price'],
                    'quantity' => $newQty,
                    'remaining_qty' => round((float) $existing->remaining_qty + $delta, 4),
                ]);
            } else {
                $item = PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $line['product']->id,
                    'product_variation_id' => $line['variation']?->id,
                    'purchase_price' => $line['cost_price'],
                    'price' => $line['price'],
                    'quantity' => $line['quantity'],
                    'remaining_qty' => $line['quantity'],
                ]);

                StockReconciliationService::reconcileNewBatch($item);
            }
        }

        // 2. Remove items no longer present (safe — we already checked nothing sold).
        foreach ($existingItems as $key => $existing) {
            if (isset($desired[$key]))
                continue;
            $existing->delete();
        }
    }

    /**
     * Delete the purchase linked to an import (only safe when nothing has been sold).
     */
    protected function deleteImportPurchase(Import $import): void
    {
        $purchase = Purchase::find($import->purchase_id);
        if (!$purchase) {
            $import->update(['purchase_id' => null]);
            return;
        }

        foreach ($purchase->items as $item) {
            $consumed = round((float) $item->quantity - (float) $item->remaining_qty, 4);
            if ($consumed > 0) {
                throw ValidationException::withMessages([
                    'import_products' => "Cannot remove the linked purchase — some items have already been sold.",
                ]);
            }
        }

        $purchase->items()->delete();
        $purchase->delete();

        $import->update(['purchase_id' => null]);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $businessId = session('business_id');

            if (!$businessId) {
                return response()->json(['error' => 'No business selected'], 422);
            }

            $importsQuery = Import::with(['supplier', 'godown', 'expenses', 'currency'])->where('business_id', $businessId)
                ->orderBy('id', 'desc');

            /* ---------- Date Filter ---------- */
            if ($request->filled('start_date')) {
                $startDate = Carbon::createFromFormat('Y-m-d', $request->input('start_date'))->startOfDay();
                $importsQuery->where('created_at', '>=', $startDate);
            }

            if ($request->filled('end_date')) {
                $endDate = Carbon::createFromFormat('Y-m-d', $request->input('end_date'))->endOfDay();
                $importsQuery->where('created_at', '<=', $endDate);
            }

            $datatable = DataTables::of($importsQuery)
                ->addIndexColumn()

                ->addColumn('import_no', fn($d) => '<strong>' . e($d->import_no) . '</strong>')
                ->addColumn('container_code', fn($d) => $d->container_code ? e($d->container_code) : '-')
                ->addColumn('supplier', fn($d) => $d->supplier?->name ? e($d->supplier->name) : '-')
                ->addColumn('godown', fn($d) => $d->godown?->name ? e($d->godown->name) : '-')
                ->addColumn('p_date', fn($d) => $d->p_date ? Carbon::parse($d->p_date)->format('Y-m-d') : '-')
                ->addColumn('clear_date', fn($d) => $d->clear_date ? Carbon::parse($d->clear_date)->format('Y-m-d') : '-')

                ->addColumn('currency', function ($d) {
                    return $d->currency ? '<span class="badge bg-primary">' . e($d->currency->code) . '</span>' : '-';
                })

                ->addColumn('inv_amount', fn($d) => number_format((float) $d->inv_amount, 2, '.', ','))
                ->addColumn('total_expense', fn($d) => number_format((float) $d->total_expense, 2, '.', ','))


                ->addColumn('created_at', fn($d) => $d->created_at ? $d->created_at->format('Y-m-d') : '-')


                ->addColumn('action', function ($d) {
                    $detailsUrl = route('backend.admin.imports.details', $d->id);
                    $editUrl = route('backend.admin.imports.edit', $d->id);
                    $deleteUrl = route('backend.admin.imports.destroy', $d->id);
                    $csrf = csrf_token();

                    return '
                        <div class="d-flex gap-1 flex-nowrap">
                            <button type="button"
                                class="btn-expand btn-outline-success view-import-details"
                                data-url="' . e($detailsUrl) . '">
                                <i class="fas fa-eye"></i> <span>Details</span>
                            </button>

                            <a href="' . e($editUrl) . '" class="btn-expand btn-outline-primary">
                                <i class="fas fa-edit"></i> <span>Edit</span>
                            </a>

                            <form action="' . e($deleteUrl) . '" method="POST"
                                style="display:inline;"
                                onsubmit="return confirm(\'Are you sure you want to delete this record?\')">
                                <input type="hidden" name="_token" value="' . e($csrf) . '">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="btn-expand btn-outline-danger">
                                    <i class="fas fa-trash"></i> <span>Delete</span>
                                </button>
                            </form>
                        </div>
                    ';
                })


                ->rawColumns(['import_no', 'currency', 'action']);

            return $datatable->toJson();
        }

        return view('backend.imports.index');
    }


    public function details($id)
    {
        $businessId = $this->getBusinessId();

        $import = Import::with(['products.product', 'products.unit', 'products.qtyUnit', 'expenses.category'])
            ->where('business_id', $businessId)->findOrFail($id);

        $categoryPool = $import->expenses->groupBy('category_id')
            ->map(fn($g) => $g->sum(fn($e) => (float) $e->amount));

        $fixedExpenses = collect(['freight_expense', 'fta_expense', 'fitting_expense', 'co_expense', 'other_expense'])
            ->mapWithKeys(fn($f) => [$f => (float) $import->{$f}]);

        $globalExpense = (float) $fixedExpenses->sum();

        $costItems = [];
        foreach ($import->products as $item) {
            $costItems[$item->id] = [
                'category_id' => $item->product?->category_id,
                'base_qty' => (float) $item->total_pcs,
                'purchase_price' => (float) $item->price_pc,
            ];
        }

        $calculatedCosts = $this->calculateLandedCosts($globalExpense, $categoryPool, $costItems);

        return response()->json([
            'import_no' => $import->import_no,

            'products' => $import->products->map(function ($item) use ($calculatedCosts) {
                $costInfo = $calculatedCosts[$item->id] ?? [
                    'cost_price' => round((float) $item->price_pc, 4),
                    'cat_per_pc' => 0,
                    'global_per_pc' => 0,
                    'cat_total' => 0,
                    'cat_qty' => 0,
                    'global_expense' => 0,
                    'total_base_qty' => 0,
                ];

                $totalPieces = (float) $item->total_pcs;
                $price = (float) $item->price_pc;

                return [
                    'name' => $item->product?->name ?? '-',
                    'sku' => $item->product?->sku ?? '-',
                    'unit' => $item->unit?->title ?? '-',
                    'qty_unit' => $item->qtyUnit?->title ?? ($item->unit?->title ?? '-'),
                    'pcs_per_carton' => (float) $item->pcs_per_carton,
                    'ctn_qty' => (float) $item->ctn_qty,
                    'total_pcs' => $totalPieces,
                    'price_pc' => $price,
                    'cost_pc' => $costInfo['cost_price'],
                    'sale_price' => $item->sale_price,
                    'sale_percent' => $item->sale_percent,
                    'amount' => round($totalPieces * $price, 2),

                    'cost_breakdown' => [
                        'price_pc' => round($price, 4),
                        'category_per_pc' => $costInfo['cat_per_pc'],
                        'global_per_pc' => $costInfo['global_per_pc'],
                        'category_total' => $costInfo['cat_total'],
                        'category_pcs' => $costInfo['cat_qty'],
                        'global_total' => $costInfo['global_expense'],
                        'total_pcs' => $costInfo['total_base_qty'],
                    ],
                ];
            })->values(),

            'expenses' => $import->expenses->map(fn($expense) => [
                'category' => $expense->category?->name ?? '-',
                'cbf_cbm' => $expense->cbf_cbm,
                'amount' => $expense->amount,
            ])->values(),

            'fixed_expenses' => [
                ['label' => 'Freight Expense', 'amount' => $fixedExpenses['freight_expense']],
                ['label' => 'F.T.A Expense', 'amount' => $fixedExpenses['fta_expense']],
                ['label' => 'Fitting Expense', 'amount' => $fixedExpenses['fitting_expense']],
                ['label' => 'C.O Expense', 'amount' => $fixedExpenses['co_expense']],
                ['label' => 'Other Expense', 'amount' => $fixedExpenses['other_expense']],
            ],
            'fixed_expenses_total' => round($globalExpense, 2),
        ]);
    }


    public function create()
    {
        $businessId = $this->getBusinessId();

        $suppliers = Supplier::where('business_id', $businessId)->get();
        $categories = Category::where('business_id', $businessId)->where('status', true)->get();
        $godowns = Godown::where('business_id', $businessId)->where('status', true)->get();

        $units = Unit::get(['id', 'title']);
        $packings = Packing::where('is_active', true)
            ->with(['baseUnit', 'levels.unit'])
            ->where(function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
                if (!$businessId) $q->orWhereNull('business_id');
            })->get();
        $currencies = Currency::get(['id', 'code', 'name', 'symbol']);
        $nextImportNo = $this->peekNextImportNumber($businessId);

        return view('backend.imports.create', compact('categories', 'suppliers', 'godowns', 'units', 'packings', 'currencies', 'nextImportNo'));
    }

    public function store(Request $request)
    {
        $businessId = $this->getBusinessId();

        $this->validateImportRequest($request, $businessId);

        $data = $this->sanitize($request->only($this->allowedImportFields()));
        $data['business_id'] = $businessId;
        $this->computeTotals($data);

        DB::transaction(function () use ($data, $request, $businessId) {
            $data['import_no'] = $this->generateImportNumber($businessId);

            $import = Import::create($data);
            $this->syncExpenses($import, $request->input('expenses', []), $businessId);

            $productRows = $this->normalizeImportRows($request->input('import_products', []), $businessId);
            $this->syncImportProducts($import, $productRows, $businessId);
            $this->createPurchaseFromImport($import, $productRows, $businessId);
        });

        return redirect()->route('backend.admin.imports.index')->with('success', 'Import record created successfully.');
    }

    public function edit(Import $import)
    {
        $businessId = $this->getBusinessId();

        if ($import->business_id != $businessId) {
            throw ValidationException::withMessages([
                'business_id' => ['The session Business ID does not match the Edit Business ID.'],
            ]);
        }


        $suppliers = Supplier::where('business_id', $businessId)->get();
        $categories = Category::where('business_id', $businessId)->where('status', true)->get();
        $godowns = Godown::where('business_id', $businessId)->where('status', true)->get();
        $units = Unit::get(['id', 'title']);
        $packings = Packing::where('is_active', true)
            ->with(['baseUnit', 'levels.unit'])
            ->where(function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
                if (!$businessId) $q->orWhereNull('business_id');
            })->get();

        $import->load('expenses.category', 'products.product', 'products.unit', 'products.qtyUnit', 'products.packing.levels.unit', 'products.variation');

        $currencies = Currency::get(['id', 'code', 'name', 'symbol']);

        return view('backend.imports.edit', compact('import', 'categories', 'suppliers', 'godowns', 'units', 'packings', 'currencies'));
    }

    public function update(Request $request, $id)
    {
        $businessId = $this->getBusinessId();
        $import = Import::forCurrentBusiness()->findOrFail($id);

        $this->validateImportRequest($request, $businessId, $import->id);

        $data = $this->sanitize($request->only($this->allowedImportFields()));
        $this->computeTotals($data);

        DB::transaction(function () use ($import, $data, $request, $businessId) {
            $import->update($data);
            $this->syncExpenses($import, $request->input('expenses', []), $businessId);

            $productRows = $this->normalizeImportRows($request->input('import_products', []), $businessId);
            $this->syncImportProducts($import, $productRows, $businessId);
            $this->createPurchaseFromImport($import, $productRows, $businessId);
        });

        return redirect()->route('backend.admin.imports.index')->with('success', 'Import record updated successfully.');
    }

    private function validateImportRequest(Request $request, int $businessId, ?int $importId = null): void
    {
        $request->validate([
            'import_no' => [
                'nullable',
                'string',
                Rule::unique('imports', 'import_no')->where(fn($q) => $q->where('business_id', $businessId))->ignore($importId),
            ],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('business_id', $businessId)],
            'godown_id' => ['nullable', Rule::exists('godowns', 'id')->where('business_id', $businessId)],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],

            'ex_rate' => ['nullable', 'numeric', 'min:0.000001', 'required_with:currency_id'],
            'inv_amount' => ['nullable', 'numeric', 'min:0'],
            'inv_amount_pkr' => ['nullable', 'numeric', 'min:0', 'required_without:inv_amount'],

            'expenses' => ['nullable', 'array'],
            'expenses.*.category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'expenses.*.amount' => ['nullable', 'numeric', 'min:0'],
            'expenses.*.cbf_cbm' => ['nullable', 'numeric', 'min:0'],

            'import_products' => ['nullable', 'array'],
            'import_products.*.product_id' => ['nullable', 'integer'],
            'import_products.*.product_variation_id' => ['nullable', 'integer'],
            'import_products.*.packing_id' => ['nullable', 'exists:packings,id'],
            'import_products.*.qty_unit_id' => ['nullable', 'exists:units,id', 'required_with:import_products.*.packing_id'],
            'import_products.*.unit_id' => ['nullable', 'integer'],
            'import_products.*.pcs_per_carton' => ['nullable', 'numeric', 'min:0.0001', 'required_without:import_products.*.packing_id'],
            'import_products.*.ctn_qty' => ['nullable', 'numeric', 'min:0'],
            'import_products.*.price_pc' => 'nullable|numeric|min:0',
            'import_products.*.sale_price' => 'nullable|numeric|min:0',
            'import_products.*.sale_percent' => 'nullable|numeric',
        ], [
            'ex_rate.required_with' => 'Exchange rate is required when a currency is selected.',
            'inv_amount_pkr.required_without' => 'Enter either Inv Amount or Inv Amount / PKR.',
        ]);

        // Validate that product + variation combos are unique
        $rows = $request->input('import_products', []);
        $seen = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $productId = $row['product_id'] ?? null;
            if (empty($productId)) {
                continue;
            }

            $variationId = $row['product_variation_id'] ?? null;
            $key = $productId . ':' . ($variationId ?? '0');

            if (isset($seen[$key])) {
                $errors[] = "The product/variation combination in row " . ($index + 1)
                    . " is duplicated (same as row " . ($seen[$key] + 1) . ").";
                continue;
            }

            $seen[$key] = $index;
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages(['import_products' => $errors,]);
        }
    }

    protected function normalizeImportRows(array $rows, int $businessId): array
    {
        foreach ($rows as $i => $row) {
            if (empty($row['product_id'])) continue;
            $product = Product::where('business_id', $businessId)->findOrFail($row['product_id']);
            $entered = (float) ($row['ctn_qty'] ?? 0);

            if (!empty($row['packing_id'])) {
                UomConverter::assertPackingMatchesProduct((int) $row['packing_id'], $product);
                $c = UomConverter::toBase((int) $row['packing_id'], (int) $row['qty_unit_id'], $entered);
                $rows[$i]['pcs_per_carton'] = $c['factor'];
                $rows[$i]['total_pcs']      = $c['total_base'];
                $rows[$i]['unit_id']        = $product->unit_id;
            } else { // legacy payload
                $rows[$i]['total_pcs'] = round((float) ($row['pcs_per_carton'] ?? 0) * $entered, 4);
            }
            $rows[$i]['amount'] = round($rows[$i]['total_pcs'] * (float) ($row['price_pc'] ?? 0), 2);
        }
        return $rows;
    }

    /**
     * Compute landed cost per base unit for given items and expenses (Decision A).
     */
    private function calculateLandedCosts(float $globalExpense, $categoryPool, array $items): array
    {
        $totalBaseQty = (float) collect($items)->sum('base_qty');
        $qtyByCategory = collect($items)
            ->groupBy(fn($i) => (string) ($i['category_id'] ?? ''))
            ->map(fn($g) => $g->sum('base_qty'));

        $globalPerPc = $totalBaseQty > 0 ? ($globalExpense / $totalBaseQty) : 0;

        $results = [];
        foreach ($items as $k => $item) {
            $catId = (string) ($item['category_id'] ?? '');
            $catQty = (float) ($qtyByCategory[$catId] ?? 0);
            $catTotal = (float) ($categoryPool[$catId] ?? 0);
            $catPerPc = $catQty > 0 ? ($catTotal / $catQty) : 0;
            $purchasePrice = (float) ($item['purchase_price'] ?? 0);

            $results[$k] = [
                'cost_price' => round($purchasePrice + $catPerPc + $globalPerPc, 4),
                'cat_per_pc' => round($catPerPc, 4),
                'global_per_pc' => round($globalPerPc, 4),
                'cat_total' => round($catTotal, 2),
                'cat_qty' => round($catQty, 4),
                'global_expense' => round($globalExpense, 2),
                'total_base_qty' => round($totalBaseQty, 4),
            ];
        }

        return $results;
    }

    private function computeTotals(array &$data): void
    {
        $fixed = ['freight_expense', 'fta_expense', 'fitting_expense', 'co_expense', 'other_expense'];
        $total = 0;
        foreach ($fixed as $field) {
            $total += (float) ($data[$field] ?? 0);
        }

        $data['total_expense'] = round($total, 2);
    }

    protected function syncImportProducts(Import $import, array $rows, int $businessId): void
    {
        $import->products()->delete();

        $validUnitIds = Unit::pluck('id')->all();

        foreach ($rows as $row) {
            if (empty($row['product_id'])) {
                continue;
            }
            $productId = (int) ($row['product_id'] ?? 0);
            $unitId = (int) ($row['unit_id'] ?? 0);

            // Skip rows without a valid packing unit — the user hasn't filled them yet.
            if (!$unitId || !in_array($unitId, $validUnitIds, true)) {
                throw ValidationException::withMessages([
                    'import_products' => "Select a packing unit for the selected product.",
                ]);
            }

            // Ensure the product belongs to the current business.
            $product = Product::where('business_id', $businessId)->where('id', $productId)->first();
            if (!$product) {
                throw ValidationException::withMessages([
                    'import_products' => 'One of the selected products is invalid.',
                ]);
            }

            $packing = (float) ($row['pcs_per_carton'] ?? 0);
            $cartons = (float) ($row['ctn_qty'] ?? 0);
            $pricePerPiece = (float) ($row['price_pc'] ?? 0);

            $totalPieces = (float) ($row['total_pcs'] ?? round($packing * $cartons, 4));
            $amount = (float) ($row['amount'] ?? round($totalPieces * $pricePerPiece, 2));

            // Ignore completely empty product rows.
            if ($productId <= 0) {
                continue;
            }

            $import->products()->create([
                'product_id' => $product->id,
                'product_variation_id' => $row['product_variation_id'] ?? null,
                'packing_id' => !empty($row['packing_id']) ? (int) $row['packing_id'] : null,
                'qty_unit_id' => !empty($row['qty_unit_id']) ? (int) $row['qty_unit_id'] : null,
                'unit_id' => (int) $row['unit_id'],
                'pcs_per_carton' => $packing,
                'ctn_qty' => $cartons,
                'total_pcs' => $totalPieces,
                'price_pc' => $pricePerPiece,
                'sale_price' => $row['sale_price'] ?? null,
                'sale_percent' => $row['sale_percent'] ?? null,
                'amount' => $amount,
            ]);
        }
    }

    protected function syncExpenses(Import $import, array $rows, int $businessId): void
    {
        // Keep rows that have a category and at least ONE non-zero value
        $clean = collect($rows)->filter(function ($r) {
            if (empty($r['category_id'])) {
                return false;
            }
            $amount = (float) ($r['amount'] ?? 0);
            $cbfCbm = (float) ($r['cbf_cbm'] ?? 0);
            return $amount > 0 || $cbfCbm > 0;
        })->keyBy('category_id')->values();

        // Validate categories belong to this business & are active
        $validIds = Category::where('business_id', $businessId)->whereIn('id', $clean->pluck('category_id'))
            ->where('status', true)->pluck('id')->all();

        // Wipe old rows, then recreate
        $import->expenses()->delete();

        foreach ($clean as $row) {
            if (!in_array($row['category_id'], $validIds)) {
                continue;
            }

            $import->expenses()->create([
                'category_id' => $row['category_id'],
                'amount' => $row['amount'] ?? 0,
                'cbf_cbm' => $row['cbf_cbm'] ?? 0,
            ]);
        }
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $import = Import::forCurrentBusiness()->findOrFail($id);

            if ($import->purchase_id) {
                $this->deleteImportPurchase($import);
            }

            $import->expenses()->delete();
            $import->delete();

            return redirect()->route('backend.admin.imports.index')->with('success', 'Import record deleted successfully.');
        });
    }

    public function searchProducts(Request $request)
    {
        $businessId = $this->getBusinessId();
        $term = trim((string) $request->input('q', ''));

        $query = Product::where('business_id', $businessId)
            ->where('status', 1)
            ->select('id', 'name', 'sku', 'has_variations', 'category_id', 'unit_id', 'default_packing_id');

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%");
            });
        }

        // Cap results — we only need enough to render the dropdown.
        $products = $query->orderBy('name')->limit(30)->get();

        // Load variations ONLY for the products we're returning (not the whole catalogue).
        $variations = ProductVariation::where('business_id', $businessId)
            ->whereIn('product_id', $products->pluck('id'))
            ->with(['values:id,value'])                 // <-- important
            ->get(['id', 'product_id'])                 // <-- real columns only
            ->groupBy('product_id');

        return response()->json([
            'results' => $products->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku ?? '',
                'category_id' => $p->category_id ?? null,
                'unit_id' => $p->unit_id,
                'default_packing_id' => $p->default_packing_id,
                'has_variations' => (bool) $p->has_variations,
                'variations' => $p->has_variations
                    ? ($variations[$p->id] ?? collect())->map(fn($v) => [
                        'id' => $v->id,
                        'label' => $v->label,           // accessor -> uses `values`
                    ])->values()
                    : [],
            ])->values(),
        ]);
    }

    public function getExchangeRate(Request $request)
    {
        $request->validate([
            'currency_id' => 'required|exists:currencies,id',
        ]);

        $currency = Currency::findOrFail($request->currency_id);
        $code = strtolower($currency->code);

        if ($code === 'pkr') {
            return response()->json(['rate' => 1.0]);
        }

        $cacheKey = "exchange_rate_{$code}_pkr";

        // 1. Try cache first
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            if ($cached === 'FAILED') {
                return response()->json([
                    'rate' => null,
                    'message' => 'Currency exchange rate not available currently. Please enter manually.',
                ], 422);
            }

            return response()->json(['rate' => $cached]);
        }

        // 2. Cache miss → try multiple providers
        $rate = null;

        // Provider list: primary fawazahmed0 + backup mirror for EUR (and as general fallback)
        $providers = [
            "https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/{$code}.min.json",
            "https://latest.currency-api.pages.dev/v1/currencies/{$code}.min.json",
        ];

        // For EUR, add independent backup providers (same API structure, different maintainers)
        if ($code === 'eur') {
            $providers[] = "https://cdn.jsdelivr.net/gh/irfanokr/currency-api@main/v1/currencies/eur.min.json";
            $providers[] = "https://cdn.jsdelivr.net/gh/NemesisX1/currency-api@main/v1/currencies/eur.min.json";
        }

        foreach ($providers as $url) {
            try {
                $response = Http::timeout(6)->get($url);

                if (!$response->successful()) {
                    continue;
                }

                $data = $response->json();

                // Compatible with both response structures: fawazahmed0 uses $data[$code]['pkr'], some mirrors use $data['pkr']
                $candidate = $data[$code]['pkr'] ?? $data['pkr'] ?? null;

                if ($candidate !== null && $candidate > 0) {
                    $rate = $candidate;
                    break; // Successfully obtained, exit loop
                }
            } catch (\Throwable $e) {
                Log::warning('Currency API attempt failed', [
                    'url' => $url,
                    'currency' => $code,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        // 3. All providers failed
        if ($rate === null || $rate <= 0) {
            Cache::put($cacheKey, 'FAILED', now()->addMinutes(5));

            return response()->json([
                'rate' => null,
                'message' => 'Currency exchange rate not available currently. Please enter manually.',
            ], 422);
        }

        // 4. Success: round, cache, return
        $roundedRate = round((float) $rate, 2);
        Cache::put($cacheKey, $roundedRate, now()->addHours(6));

        return response()->json(['rate' => $roundedRate]);
    }
}