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
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Log;

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
                    $query->where('business_id', $businessId);
                })
                ->with(['supplier', 'items.product', 'items.variation.values'])
                ->orderByDesc('id')
                ->get();


            return DataTables::of($purchases)
                ->addIndexColumn()
                ->addColumn('supplier', fn($data) => $data->supplier->name)
                ->addColumn('id', function ($data) {
                    return '#' . $data->id;
                })
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
                ->addColumn('created_at', fn($data) => \Carbon\Carbon::parse($data->date)->format('d M, Y')) // Using Carbon for formatting
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
                ->rawColumns(['supplier', 'id', 'products', 'total', 'created_at', 'action'])
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

        // abort_if(!auth()->user()->can('purchase_create'), 403);
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

        // abort_if(!auth()->user()->can('purchase_update'), 403);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Purchase $purchase)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('purchase_update'), 403);

        // abort_if(!auth()->user()->can('purchase_update'), 403);
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Purchase $purchase)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('purchase_delete'), 403);


        // abort_if(!auth()->user()->can('purchase_delete'), 403);
        //
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

        $validated = $request->validate([
            'stock_file' => 'required|file|mimes:csv,xlsx,xls|max:2048',
        ]);

        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('product_create'), 403);

        $businessId = session('business_id');

        Log::info('Stock Import Started', [
            'business_id' => $businessId,
            'user_id' => auth()->id(),
            'file_name' => $request->file('stock_file')->getClientOriginalName()
        ]);

        DB::beginTransaction();

        try {
            $file = $request->file('stock_file');
            $extension = $file->getClientOriginalExtension();

            Log::info('File Extension Detected', ['extension' => $extension]);

            // Read file based on extension
            if ($extension === 'csv') {
                $data = $this->readCSV($file);
            } else {
                $data = $this->readExcel($file);
            }


            $results = [
                'success' => [],
                'errors' => [],
                'total' => count($data),
                'updated' => 0,
                'failed' => 0
            ];

            // Variables for purchase totals
            $purchaseProducts = [];
            $subTotal = 0;

            foreach ($data as $index => $row) {
                $rowNumber = $index + 2;


                // Map Excel columns to expected fields
                $slug = '';
                $hsCode = '';
                $quantity = 0;

                // Try to find the values in different possible column names
                foreach ($row as $key => $value) {
                    $keyLower = strtolower(trim($key));
                    $valueTrimmed = is_string($value) ? trim($value) : $value;

                    // Match ITEMS column
                    if (in_array($keyLower, ['items', 'item', 'product', 'slug'])) {
                        $slug = $valueTrimmed;
                    }

                    // Match HS CODE column
                    if (in_array($keyLower, ['hs code', 'hscode', 'hs_code', 'h.s code'])) {
                        // Handle HS Code - preserve exact format
                        if (is_numeric($valueTrimmed)) {
                            $hsCode = strval($valueTrimmed);

                            // Check if it's in scientific notation
                            if (strpos($hsCode, 'E') !== false || strpos($hsCode, 'e') !== false) {
                                $hsCode = sprintf('%.10f', $valueTrimmed);
                                $hsCode = rtrim(rtrim($hsCode, '0'), '.');
                            }
                        } else {
                            $hsCode = strval($valueTrimmed);
                        }

                        // Remove commas, spaces, and other formatting
                        $hsCode = str_replace([',', ' ', "\t"], '', $hsCode);
                        $hsCode = trim($hsCode);
                    }

                    // Match No. of CONT column
                    if (in_array($keyLower, ['no. of cont', 'no of cont', 'no.of cont', 'quantity', 'qty', 'cont'])) {
                        $quantity = floatval($valueTrimmed);
                    }
                }


                // Validate required fields
                if (empty($slug) || empty($hsCode) || empty($quantity)) {
                    $error = "Row {$rowNumber}: ITEMS ('{$slug}'), HS CODE ('{$hsCode}'), and No. of CONT ('{$quantity}') are required.";
                    $results['errors'][] = $error;
                    $results['failed']++;

                    continue;
                }

                // Validate quantity
                if ($quantity <= 0) {
                    $error = "Row {$rowNumber}: No. of CONT must be greater than 0.";
                    $results['errors'][] = $error;
                    $results['failed']++;

                    continue;
                }

                // Find product by Slug + HsCode and Business ID
                // Try exact match first
                $product = Product::where('slug', $slug)
                    ->where('hsCode', $hsCode)
                    ->where('business_id', $businessId)
                    ->first();

                // If no exact match, try matching by removing trailing zeros/dots from both sides
                if (!$product) {
                    $hsCodeNormalized = rtrim(rtrim($hsCode, '0'), '.');


                    // Find products with matching slug and business
                    $candidateProducts = Product::where('slug', $slug)
                        ->where('business_id', $businessId)
                        ->get();

                    // Try to match by normalized HS code
                    foreach ($candidateProducts as $candidate) {
                        $dbHsCodeNormalized = rtrim(rtrim($candidate->hsCode, '0'), '.');

                        if ($dbHsCodeNormalized === $hsCodeNormalized) {
                            $product = $candidate;
                            break;
                        }
                    }
                }

                if (!$product) {
                    $error = "Row {$rowNumber}: Product with ITEMS '{$slug}' and HS CODE '{$hsCode}' not found.";
                    $results['errors'][] = $error;
                    $results['failed']++;
                    continue;
                }

                // Store product info for purchase creation
                $purchasePrice = $product->purchase_price ?? 0;
                $price = $product->price ?? 0;
                $itemTotal = $purchasePrice * $quantity;

                $purchaseProducts[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'purchase_price' => $purchasePrice,
                    'price' => $price,
                    'total' => $itemTotal
                ];

                $subTotal += $itemTotal;

                $results['updated']++;

                Log::info("Row {$rowNumber} Product Prepared for Purchase", [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'purchase_price' => $purchasePrice
                ]);
            }

            // Create Purchase record if we have valid products
            if (count($purchaseProducts) > 0) {
                // Get or create default supplier for imports
                // $defaultSupplier = Supplier::findOrFail(1);


                $purchase = Purchase::create([
                    'supplier_id' => 1,
                    'user_id' => auth()->id(),
                    'business_id' => $businessId,
                    'sub_total' => $subTotal,
                    'tax' => 0,
                    'discount_value' => 0,
                    'shipping' => 0,
                    'grand_total' => $subTotal,
                    'date' => now()->toDateString(),
                    'status' => 1,
                ]);

                Log::info('Purchase Created', [
                    'purchase_id' => $purchase->id,
                    'supplier_id' => 1,
                    'grand_total' => $subTotal
                ]);

                // Create purchase items and update product quantities
                foreach ($purchaseProducts as $item) {
                    $product = $item['product'];
                    $oldStock = $product->quantity ?? 0;

                    // Create purchase item
                    $purchaseItem = PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $product->id,
                        'purchase_price' => $item['purchase_price'],
                        'price' => $item['price'],
                        'quantity' => $item['quantity'],
                        'remaining_qty' => $item['quantity'],
                    ]);

                    \App\Services\StockReconciliationService::reconcileNewBatch($purchaseItem);

                    // Update product stock
                    $product->increment('quantity', $item['quantity']);
                    $newStock = $product->quantity;

                    $success = "Product '{$product->name}' (ITEMS: {$product->slug}, HS CODE: {$product->hsCode}) stock updated from {$oldStock} to {$newStock}.";
                    $results['success'][] = $success;

                    Log::info("Product Stock Updated", [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'old_stock' => $oldStock,
                        'new_stock' => $newStock,
                        'added_quantity' => $item['quantity']
                    ]);
                }
            }

            DB::commit();

            Log::info('Stock Import Completed', [
                'total' => $results['total'],
                'updated' => $results['updated'],
                'failed' => $results['failed'],
                'purchase_id' => $purchase->id ?? null
            ]);

            // Prepare session message
            $message = "Stock import completed. ";
            $message .= "{$results['updated']} products imported successfully. ";

            if (isset($purchase)) {
                $message .= "Purchase #{$purchase->id} created with total: " . number_format($purchase->grand_total, 2) . ". ";
            }

            if ($results['failed'] > 0) {
                $message .= "{$results['failed']} rows failed.";
                session()->flash('import_errors', array_slice($results['errors'], 0, 50));
                session()->flash('successes', $results['success']);
                return redirect()->route('backend.admin.purchase.index')
                    ->with('warning', $message);
            }

            session()->flash('successes', $results['success']);
            return redirect()->route('backend.admin.purchase.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Stock Import Failed', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Error importing stock: ' . $e->getMessage());
        }
    }

    private function readExcel($file)
    {

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();

            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();

            $allRows = [];

            // Read rows and preserve cell values (especially for HS CODE)
            for ($row = 1; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $cell = $worksheet->getCell($col . $row);

                    // Get all possible values
                    $formattedValue = $cell->getFormattedValue();
                    $rawValue = $cell->getValue();
                    $dataType = $cell->getDataType();
                    $numberFormat = $cell->getStyle()->getNumberFormat()->getFormatCode();

                    // Determine the best value to use
                    if ($dataType === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING) {
                        // It's text, use raw value as-is
                        $value = $rawValue;
                    } else if ($numberFormat === '@') {
                        // Formatted as text
                        $value = $formattedValue;
                    } else {
                        // For numeric values, try to preserve as much precision as possible
                        if (is_numeric($rawValue)) {
                            // Convert to string with maximum precision
                            $value = rtrim(rtrim(sprintf('%.15f', $rawValue), '0'), '.');
                        } else {
                            $value = $rawValue;
                        }

                        // Clean formatted value
                        $formattedClean = str_replace([',', ' ', '$', '€', '£'], '', $formattedValue);

                        // If formatted value has more decimal places, prefer it
                        if (strpos($formattedClean, '.') !== false && strpos($value, '.') !== false) {
                            $formattedDecimals = strlen(substr($formattedClean, strpos($formattedClean, '.') + 1));
                            $valueDecimals = strlen(substr($value, strpos($value, '.') + 1));

                            if ($formattedDecimals > $valueDecimals) {
                                $value = $formattedClean;
                            }
                        }
                    }

                    $rowData[] = $value;
                }
                $allRows[] = $rowData;
            }

            // Find the header row (look for row containing "Items" or "ITEMS")
            $headerRowIndex = null;
            foreach ($allRows as $index => $row) {
                foreach ($row as $cell) {
                    if (stripos($cell, 'items') !== false || stripos($cell, 'hs code') !== false) {
                        $headerRowIndex = $index;
                        break 2;
                    }
                }
            }

            if ($headerRowIndex === null) {
                Log::error('Could not find header row in Excel');
                throw new \Exception('Could not find header row with ITEMS, HS CODE columns');
            }


            // Get header and normalize
            $header = array_map('strtolower', array_map('trim', $allRows[$headerRowIndex]));

            $data = [];
            // Read data rows after header
            for ($i = $headerRowIndex + 1; $i < count($allRows); $i++) {
                $row = $allRows[$i];

                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                if (count($row) >= count($header)) {
                    // Take only the columns we have headers for
                    $rowData = array_slice($row, 0, count($header));
                    $data[] = array_combine($header, $rowData);
                }
            }

            return $data;

        } catch (\Exception $e) {
            Log::error('Excel Reading Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function readCSV($file)
    {

        try {
            $data = [];
            $handle = fopen($file->getRealPath(), 'r');

            if ($handle === false) {
                Log::error('Failed to open CSV file');
                throw new \Exception('Could not open CSV file');
            }

            $allRows = [];
            while (($row = fgetcsv($handle)) !== false) {
                $allRows[] = $row;
            }
            fclose($handle);

            // Find the header row
            $headerRowIndex = null;
            foreach ($allRows as $index => $row) {
                foreach ($row as $cell) {
                    if (stripos($cell, 'items') !== false || stripos($cell, 'hs code') !== false) {
                        $headerRowIndex = $index;
                        break 2;
                    }
                }
            }

            if ($headerRowIndex === null) {
                Log::error('Could not find header row in CSV');
                throw new \Exception('Could not find header row with ITEMS, HS CODE columns');
            }


            $header = array_map('strtolower', array_map('trim', $allRows[$headerRowIndex]));

            // Read data rows after header
            for ($i = $headerRowIndex + 1; $i < count($allRows); $i++) {
                $row = $allRows[$i];
                if (count($row) === count($header) && !empty(array_filter($row))) {
                    $data[] = array_combine($header, $row);
                }
            }

            return $data;

        } catch (\Exception $e) {
            Log::error('CSV Reading Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
