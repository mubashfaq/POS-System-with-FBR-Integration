<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Godown;
use App\Models\Import;
use App\Models\ProductVariation;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Unit;
use App\Services\StockReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'import_no',
            'container_code',
            'p_date',
            'clear_date',
            'currency',
            'ex_rate',
            'inv_amount',
            'total_expense_1',
            'inv_amount_pkr',
            'godown_id',
            'supplier_id',
            'address',
            'freight_expense',
            'fta_expense',
            'fitting_expense',
            'co_expense',
            'other_expense',
            'total_expense_2',
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

            $packing = (int) ($row['pcs_per_carton'] ?? 0);
            $cartons = (int) ($row['ctn_qty'] ?? 0);
            $qty = $packing * $cartons;

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

        // ---- Landed cost per piece ----
        $categoryPool = $import->expenses()->get()
            ->groupBy('category_id')
            ->map(fn($g) => $g->sum(fn($e) => (float) $e->amount + (float) $e->cbf_cbm));

        $globalExpense = collect(['freight_expense', 'fta_expense', 'fitting_expense', 'co_expense', 'other_expense'])
            ->sum(fn($f) => (float) $import->{$f});

        $totalQty = collect($desired)->sum('quantity');

        $qtyByCategory = collect($desired)
            ->groupBy(fn($l) => $l['product']->category_id)
            ->map(fn($g) => $g->sum('quantity'));

        foreach ($desired as $key => &$line) {
            $catId = $line['product']->category_id;
            $catQty = (float) ($qtyByCategory[$catId] ?? 0);

            $catPerPc = $catQty > 0 ? ((float) ($categoryPool[$catId] ?? 0)) / $catQty : 0;
            $globalPerPc = $totalQty > 0 ? $globalExpense / $totalQty : 0;

            $line['cost_price'] = round($line['purchase_price'] + $catPerPc + $globalPerPc, 2);
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

            $oldQty = (float) $existing->quantity;
            $remaining = (float) $existing->remaining_qty;
            $consumed = $oldQty - $remaining;
            $newQty = (float) $line['quantity'];

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

            $consumed = (float) $existing->quantity - (float) $existing->remaining_qty;
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
        $subTotal = collect($desired)->sum(fn($l) => $l['quantity'] * $l['cost_price']);

        $header = [
            'supplier_id' => $supplierId,
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
                $oldQty = (float) $existing->quantity;
                $newQty = (float) $line['quantity'];
                $delta = $newQty - $oldQty;

                $existing->update([
                    'purchase_price' => $line['cost_price'],
                    'price' => $line['price'],
                    'quantity' => $newQty,
                    'remaining_qty' => $existing->remaining_qty + $delta,
                ]);

                // Keep stock consistent with the batch adjustment.
                // (Only if your StockReconciliationService supports an "adjust" call —
                //  otherwise just call reconcileNewBatch on the delta as a new batch.)
                StockReconciliationService::reconcileNewBatch($existing);
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
            $consumed = (float) $item->quantity - (float) $item->remaining_qty;
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

            $importsQuery = Import::with(['supplier', 'godown', 'expenses'])->where('business_id', $businessId)->orderBy('id', 'desc');

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
                    return $d->currency ? '<span class="badge bg-primary">' . e($d->currency) . '</span>' : '-';
                })

                ->addColumn('inv_amount', fn($d) => number_format((float) $d->inv_amount, 2, '.', ','))
                ->addColumn('total_expense_1', fn($d) => number_format((float) $d->total_expense_1, 2, '.', ','))
                ->addColumn('total_expense_2', fn($d) => number_format((float) $d->total_expense_2, 2, '.', ','))

                /** Sum of all CBF/CBM across expense rows */
                ->addColumn('total_cbf_cbm', function ($d) {
                    $sum = $d->expenses->sum('cbf_cbm');
                    return number_format((float) $sum, 2, '.', ',');
                })

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

        $import = Import::with(['products.product', 'products.unit', 'expenses.category',])
            ->where('business_id', $businessId)->findOrFail($id);

        // ---- Same landed-cost formula as createPurchaseFromImport ----
        $categoryPool = $import->expenses
            ->groupBy('category_id')
            ->map(fn($g) => $g->sum(fn($e) => (float) $e->amount + (float) $e->cbf_cbm));

        $globalExpense = collect(['freight_expense', 'fta_expense', 'fitting_expense', 'co_expense', 'other_expense'])
            ->sum(fn($f) => (float) $import->{$f});

        $pcsOf = fn($item) => (float) $item->pcs_per_carton * (float) $item->ctn_qty;

        $totalPcs = $import->products->sum($pcsOf);

        $pcsByCategory = $import->products
            ->groupBy(fn($item) => $item->product?->category_id ?? '')
            ->map(fn($g) => $g->sum($pcsOf));

        $globalPerPc = $totalPcs > 0 ? $globalExpense / $totalPcs : 0;

        return response()->json([
            'import_no' => $import->import_no,

            'products' => $import->products->map(function ($item) use ($categoryPool, $pcsByCategory, $globalPerPc) {
                $pcs = (float) $item->pcs_per_carton;
                $cartons = (float) $item->ctn_qty;
                $price = (float) $item->price_pc;

                $catId = $item->product?->category_id ?? '';
                $catPcs = (float) ($pcsByCategory[$catId] ?? 0);
                $catPerPc = $catPcs > 0 ? ((float) ($categoryPool[$catId] ?? 0)) / $catPcs : 0;

                return [
                    'name' => $item->product?->name ?? '-',
                    'sku' => $item->product?->sku ?? '-',
                    'unit' => $item->unit?->title ?? '-',
                    'pcs_per_carton' => $pcs,
                    'ctn_qty' => $cartons,
                    'total_pcs' => $pcs * $cartons,
                    'price_pc' => $price,
                    'cost_pc' => round($price + $catPerPc + $globalPerPc, 2),
                    'amount' => $pcs * $cartons * $price,
                ];
            })->values(),

            'expenses' => $import->expenses->map(fn($expense) => [
                'category' => $expense->category?->name ?? '-',
                'cbf_cbm' => $expense->cbf_cbm,
                'amount' => $expense->amount,
            ])->values(),
        ]);
    }


    public function create()
    {
        $businessId = $this->getBusinessId();

        $suppliers = Supplier::where('business_id', $businessId)->get();
        $categories = Category::where('business_id', $businessId)->where('status', true)->get();
        $godowns = Godown::where('business_id', $businessId)->where('status', true)->get();


        $units = Unit::get(['id', 'title']);

        return view('backend.imports.create', compact('categories', 'suppliers', 'godowns', 'units'));
    }

    public function store(Request $request)
    {
        $businessId = $this->getBusinessId();

        $this->validateImportRequest($request, $businessId);

        $data = $this->sanitize($request->only($this->allowedImportFields()));
        $data['business_id'] = $businessId;

        DB::transaction(function () use ($data, $request, $businessId) {
            $import = Import::create($data);
            $this->syncExpenses($import, $request->input('expenses', []), $businessId);

            $productRows = $request->input('import_products', []);
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

        $import->load('expenses.category', 'products.product', 'products.unit', 'products.variation');

        return view('backend.imports.edit', compact('import', 'categories', 'suppliers', 'godowns', 'units'));
    }

    public function update(Request $request, $id)
    {
        $businessId = $this->getBusinessId();
        $import = Import::forCurrentBusiness()->findOrFail($id);

        $this->validateImportRequest($request, $businessId, $import->id);

        $data = $this->sanitize($request->only($this->allowedImportFields()));

        DB::transaction(function () use ($import, $data, $request, $businessId) {
            $import->update($data);
            $this->syncExpenses($import, $request->input('expenses', []), $businessId);

            $productRows = $request->input('import_products', []);
            $this->syncImportProducts($import, $productRows, $businessId);
            $this->createPurchaseFromImport($import, $productRows, $businessId);
        });

        return redirect()->route('backend.admin.imports.index')->with('success', 'Import record updated successfully.');
    }

    private function validateImportRequest(Request $request, int $businessId, ?int $importId = null): void
    {
        $request->validate([
            'import_no' => [
                'required',
                Rule::unique('imports', 'import_no')->where(fn($q) => $q->where('business_id', $businessId))->ignore($importId),
            ],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('business_id', $businessId)],
            'godown_id' => ['nullable', Rule::exists('godowns', 'id')->where('business_id', $businessId)],

            'expenses' => ['nullable', 'array'],
            'expenses.*.category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'expenses.*.amount' => ['nullable', 'numeric', 'min:0'],
            'expenses.*.cbf_cbm' => ['nullable', 'numeric', 'min:0'],

            'import_products' => ['nullable', 'array'],
            'import_products.*.product_id' => ['nullable', 'integer'],
            'import_products.*.product_variation_id' => ['nullable', 'integer', 'exists:product_variations,id'],
            'import_products.*.unit_id' => ['required_with:import_products.*.product_id', 'integer', 'exists:units,id'],
            'import_products.*.pcs_per_carton' => ['nullable', 'numeric', 'min:1'],
            'import_products.*.ctn_qty' => ['nullable', 'numeric', 'min:0'],
            'import_products.*.price_pc' => ['nullable', 'numeric', 'min:0'],
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
                continue;
            }

            // Ensure the product belongs to the current business.
            $product = Product::where('business_id', $businessId)->where('id', $productId)->first();
            if (!$product) {
                throw ValidationException::withMessages([
                    'import_products' => 'One of the selected products is invalid.',
                ]);
            }

            $packing = (int) ($row['pcs_per_carton'] ?? 0);
            $cartons = (int) ($row['ctn_qty'] ?? 0);
            $pricePerPiece = (float) ($row['price_pc'] ?? 0);

            $totalPieces = $packing * $cartons;
            $pricePerDozen = round($pricePerPiece * 12, 2);
            $amount = round($totalPieces * $pricePerPiece, 2);

            // Ignore completely empty product rows.
            if ($productId <= 0) {
                continue;
            }

            $import->products()->create([
                'product_id' => $product->id,
                'product_variation_id' => $row['product_variation_id'] ?? null,
                'unit_id' => (int) $row['unit_id'],
                'pcs_per_carton' => $packing,
                'ctn_qty' => $cartons,
                'total_pcs' => $totalPieces,
                'price_pc' => $pricePerPiece,
                'price_dz' => $pricePerDozen,
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
        $import = Import::forCurrentBusiness()->findOrFail($id);

        $import->expenses()->delete();
        $import->delete();

        return redirect()->route('backend.admin.imports.index')->with('success', 'Import record deleted successfully.');
    }

    public function searchProducts(Request $request)
    {
        $businessId = $this->getBusinessId();
        $term = trim((string) $request->input('q', ''));

        $query = Product::where('business_id', $businessId)
            ->where('status', 1)
            ->select('id', 'name', 'sku', 'has_variations', 'category_id');

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
}