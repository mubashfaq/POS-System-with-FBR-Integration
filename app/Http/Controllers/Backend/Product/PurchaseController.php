<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Services\StockReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('purchase_view'), 403);

        $businessId = session('business_id');
        if (!$businessId) {
            return response()->json(['error' => 'No business selected'], 422);
        }
        if ($request->ajax()) {

            $purchases = Purchase::where('business_id', $businessId)
                ->whereHas('supplier', function ($query) use ($businessId) {
                    $query->where('business_id', $businessId)
                        ->orWhere(function ($q) {
                            $q->where('id', 0)->whereNull('business_id');
                        });
                })
                ->with(['supplier', 'items.product', 'items.variation.values'])
                ->orderByDesc('id')
                ->get();


            return DataTables::of($purchases)
                ->addIndexColumn()
                ->addColumn('supplier', fn($data) => $data->supplier->name)
                ->addColumn('products', function ($data) {
                    return $data->items
                        ->map(function ($item) {
                            if (!$item->product) {
                                return '';
                            }

                            $name = e($item->product->name);

                            if ($item->variation) {
                                $name .= ' – ' . e($item->variation->label);
                            }

                            return $name . ' (' . $item->quantity . ')';
                        })
                        ->filter()
                        ->implode(', ');
                })
                ->addColumn('total', fn($data) => $data->grand_total)
                ->addColumn('created_at', fn($data) => Carbon::parse($data->date)->format('d M, Y')) // Using Carbon for formatting
                ->addColumn('action', function ($data) {
                    return '<div class="btn-group">
                        <button type="button" class="btn btn-flat">Action</button>
                        <button type="button" class="btn btn-flat dropdown-toggle dropdown-icon" data-toggle="dropdown" aria-expanded="false">
                        <span class="sr-only">Toggle Dropdown</span>
                        </button>
                        <div class="dropdown-menu" role="menu">
                            <a class="dropdown-item" href="' . route('backend.admin.purchase.create', ['purchase_id' => $data->id]) . '">
                                <i class="fas fa-edit"></i> Edit
                            </a> 
                            <a class="dropdown-item" href="' . route('backend.admin.purchase.products', $data->id) . '">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </div>';
                })
                ->rawColumns(['supplier', 'products', 'total', 'created_at', 'action'])
                ->toJson();
        }


        return view('backend.purchase.index');
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('purchase_create'), 403);

        return view('backend.purchase.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('purchase_create'), 403);

        $businessId = session('business_id');
        if (!$businessId) {
            return redirect()->back()->with('error', 'No business selected.');
        }

        if (!$request->wantsJson()) {
            return;
        }

        $validated = $request->validate([
            'purchase_id' => 'nullable|integer',
            'date' => 'nullable|date',
            'supplierId' => 'required|exists:suppliers,id',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|integer',
            'products.*.product_variation_id' => 'nullable|integer',
            'products.*.item_id' => 'nullable|integer',
            'products.*.qty' => 'required|numeric|min:0',
            'products.*.purchase_price' => 'required|numeric|min:0',
            'products.*.price' => 'required|numeric|min:0',
            'totals' => 'required|array',
            'totals.subTotal' => 'required|numeric',
            'totals.tax' => 'nullable|numeric',
            'totals.discount' => 'nullable|numeric',
            'totals.shipping' => 'nullable|numeric',
            'totals.grandTotal' => 'required|numeric',
        ]);

        $header = [
            'supplier_id' => $validated['supplierId'],
            'user_id' => auth()->id(),
            'business_id' => $businessId,
            'sub_total' => $validated['totals']['subTotal'],
            'tax' => $validated['totals']['tax'] ?? 0,
            'discount_value' => $validated['totals']['discount'] ?? 0,
            'shipping' => $validated['totals']['shipping'] ?? 0,
            'grand_total' => $validated['totals']['grandTotal'],
            'date' => $validated['date'] ?? Carbon::now()->toDateString(),
            'status' => 1,
        ];

        DB::beginTransaction();

        try {
            if (empty($validated['purchase_id'])) {
                $purchase = Purchase::create($header);

                foreach ($validated['products'] as $line) {
                    $this->addPurchaseLine($purchase, $line, $businessId);
                }
            } else {
                $purchase = Purchase::where('business_id', $businessId)->findOrFail($validated['purchase_id']);
                $purchase->update($header);

                foreach ($validated['products'] as $line) {
                    $item = !empty($line['item_id']) ? PurchaseItem::where('purchase_id', $purchase->id)->find($line['item_id']) : null;
                    $item ? $this->updatePurchaseLine($item, $line) : $this->addPurchaseLine($purchase, $line, $businessId);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            // The React page reads `message`
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json([
            'message' => 'Purchase saved successfully.',
            'purchase' => $purchase,
        ], 201);
    }

    /** Product + variation for a line, checked against the current business. */
    private function resolveStockUnit(array $line, int $businessId): array
    {
        $product = Product::where('business_id', $businessId)->findOrFail($line['id']);
        $variationId = $line['product_variation_id'] ?? null;

        if ($product->has_variations) {
            abort_if(!$variationId, 422, "Select a variation for {$product->name}.");

            $variation = ProductVariation::where('product_id', $product->id)
                ->where('business_id', $businessId)
                ->findOrFail($variationId);

            return [$product, $variation];
        }

        abort_if($variationId, 422, "{$product->name} has no variations.");

        return [$product, null];
    }

    private function addPurchaseLine(Purchase $purchase, array $line, int $businessId): void
    {
        [$product, $variation] = $this->resolveStockUnit($line, $businessId);
        $qty = (float) $line['qty'];

        $item = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'product_variation_id' => $variation?->id,
            'purchase_price' => $line['purchase_price'],
            'price' => $line['price'],
            'quantity' => $qty,
            'remaining_qty' => $qty,
        ]);

        StockReconciliationService::reconcileNewBatch($item);
    }

    /** Editing keeps the item's product and variation fixed; only prices and quantity change. */
    private function updatePurchaseLine(PurchaseItem $item, array $line): void
    {
        $oldQty = (float) $item->quantity;
        $newQty = (float) $line['qty'];
        $remainingQty = (float) $item->remaining_qty;

        $consumedQty = $oldQty - $remainingQty;

        abort_if($newQty < $consumedQty, 422, 'Quantity cannot be less than the quantity already sold.');

        $delta = $newQty - $oldQty;

        $item->update([
            'purchase_price' => $line['purchase_price'],
            'price' => $line['price'],
            'quantity' => $newQty,
            'remaining_qty' => $item->remaining_qty + $delta,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        if (!$request->wantsJson())
            return;

        $purchase = Purchase::with(['items.product', 'items.variation.values', 'supplier'])
            ->where('business_id', session('business_id'))
            ->findOrFail($id);

        return [
            'id' => $purchase->id,
            'date' => $purchase->date,
            'supplier_id' => $purchase->supplier_id,
            'supplier' => $purchase->supplier,
            'tax' => $purchase->tax,
            'discount_value' => $purchase->discount_value,
            'shipping' => $purchase->shipping,
            'items' => $purchase->items->map(fn($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_variation_id' => $item->product_variation_id,
                'name' => $item->product->name,
                'variation_label' => $item->variation?->label,
                'purchase_price' => $item->purchase_price,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'stock' => $item->variation ? $item->variation->quantity : $item->product->quantity,
            ]),
        ];
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {

        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('purchase_update'), 403);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Purchase $purchase)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('purchase_update'), 403);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Purchase $purchase)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('purchase_delete'), 403);


    }
    // purchaseProducts list by Purchase id
    public function purchaseProducts(Request $request, $id)
    {
        $businessId = session('business_id');
        $purchase = Purchase::with('items.product')->where('business_id', $businessId)->findOrFail($id);
        return view('backend.purchase.products', compact('id', 'purchase'));
    }

    public function importForm()
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('purchase_create'), 403);

        return view('backend.purchase.import');
    }

    public function import(Request $request)
    {
        // ---- Sample download ----
        if ($request->boolean('download-demo')) {
            $currentBusiness = currentUserBusiness();
            abort_if(!$currentBusiness || !$currentBusiness->hasPermission('product_view'), 403);

            $path = 'samples/stock_import_sample.xlsx';

            if (!Storage::disk('local')->exists($path)) {
                abort(404, 'Sample file not found.');
            }

            return Storage::disk('local')->download(
                $path,
                'stock_import_sample.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            );
        }

        // ---- Upload ----
        $request->validate([
            'stock_file' => 'required|file|mimes:csv,xlsx,xls|max:2048',
        ]);

        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('purchase_create'), 403);

        $businessId = session('business_id');
        if (!$businessId) {
            return redirect()->route('backend.admin.purchase.index')->with('error', 'No active business selected.');
        }

        $processedPath = null;
        $isTemp = false;

        try {
            [$processedPath, $isTemp] = $this->convertExcelFormulasToValues($request->file('stock_file'));

            if (!is_string($processedPath) || !is_file($processedPath) || filesize($processedPath) === 0) {
                throw new \RuntimeException('Processed file is empty or missing.');
            }

            $readerType = \Maatwebsite\Excel\Excel::XLSX;
            $ext = strtolower(pathinfo($processedPath, PATHINFO_EXTENSION));
            if ($ext === 'xls')
                $readerType = \Maatwebsite\Excel\Excel::XLS;
            if ($ext === 'csv')
                $readerType = \Maatwebsite\Excel\Excel::CSV;

            $import = new \App\Imports\StockImport($businessId);
            Excel::import($import, $processedPath, null, $readerType);

            $r = $import->result;

            if ($r['updated'] === 0) {
                return redirect()->route('backend.admin.purchase.index')->with(
                    'warning',
                    "No stock imported. Matched: {$r['matched']}, Failed: {$r['failed']}, Skipped: {$r['skipped']}. " .
                    (count($r['errors']) ? ' First error: ' . $r['errors'][0] : '')
                );
            }

            $message = "Stock import completed. {$r['updated']} products updated.";
            if ($r['purchase_id']) {
                $message .= " Purchase #{$r['purchase_id']} created with total: " . number_format($r['grand_total'], 2) . ".";
            }
            if ($r['failed'] > 0) {
                $message .= " {$r['failed']} rows failed.";
            }

            session()->flash('import_errors', array_slice($r['errors'], 0, 50));
            session()->flash('successes', $r['success']);

            return redirect()->route('backend.admin.purchase.index')->with($r['failed'] > 0 ? 'warning' : 'success', $message);

        } catch (\Throwable $e) {
            Log::error('❌ Stock import failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->route('backend.admin.purchase.index')->with('error', 'Stock import failed: ' . $e->getMessage());

        } finally {
            if ($isTemp && $processedPath && file_exists($processedPath)) {
                @unlink($processedPath);
            }
        }
    }

    /**
     * @return array{0:string,1:bool} [path, isTemporary]
     */
    private function convertExcelFormulasToValues($uploadedFile): array
    {
        // identical to the one in ProductController — copy it verbatim
        $originalPath = $uploadedFile->getRealPath();
        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stock_' . time() . '_' . uniqid() . '.xlsx';

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($originalPath);
            $worksheet = $spreadsheet->getActiveSheet();

            $highestRow = (int) $worksheet->getHighestRow();
            $dataStart = 9;

            $hasFormulas = false;
            if ($highestRow >= $dataStart) {
                foreach ($worksheet->getRowIterator($dataStart) as $row) {
                    foreach ($row->getCellIterator() as $cell) {
                        $v = $cell->getValue();
                        if (is_string($v) && strpos($v, '=') === 0) {
                            $hasFormulas = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$hasFormulas) {
                if (!@copy($originalPath, $tempFile)) {
                    throw new \RuntimeException('Could not copy upload to temp file.');
                }
                return [$tempFile, true];
            }

            $newSpreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $newWorksheet = $newSpreadsheet->getActiveSheet();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            for ($row = 1; $row <= $highestRow; $row++) {
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
                    $cell = $worksheet->getCell($coord);
                    try {
                        $value = $cell->getCalculatedValue();
                    } catch (\Throwable $e) {
                        $value = $cell->getValue();
                    }

                    if ($col === 2 && $row >= $dataStart) {
                        $newWorksheet->setCellValueExplicit(
                            $coord,
                            (string) $value,
                            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                        );
                    } else {
                        $newWorksheet->setCellValue($coord, $value);
                    }
                }
            }
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($newSpreadsheet))->save($tempFile);
            return [$tempFile, true];

        } catch (\Throwable $e) {
            Log::warning('⚠️ Stock formulas: ' . $e->getMessage());
            if (!is_file($tempFile))
                @copy($originalPath, $tempFile);
            return [$tempFile, true];
        }
    }
}
