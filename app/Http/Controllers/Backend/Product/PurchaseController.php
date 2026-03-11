<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
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

        // abort_if(!auth()->user()->can('purchase_view'), 403);
        $businessId = session('business_id');
        if (!$businessId) {
            return response()->json(['error' => 'No business selected'], 422);
        }
        if ($request->ajax()) {
            // $purchases = Purchase::with('supplier')
            // ->where('business_id', $businessId)
            // ->get();

            $purchases = Purchase::where('business_id', $businessId)
            ->whereHas('supplier', function($query) use ($businessId) {
                $query->where('business_id', $businessId);
            })
            ->with('supplier')
            ->get();


            return DataTables::of($purchases)
                ->addIndexColumn()
                ->addColumn('supplier', fn($data) => $data->supplier->name)
                ->addColumn('id', function ($data) {
                    return '#' . $data->id;
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
                ->rawColumns(['supplier', 'id', 'total', 'created_at', 'action'])
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

        // abort_if(!auth()->user()->can('purchase_create'), 403);

        $businessId = session('business_id'); // business_id session me hona chahiye
        if (!$businessId) {
            return redirect()->back()->with('error', 'No business selected.');
        }

        if ($request->wantsJson()) {
            // Step 1: Validate the request data
            $validatedData = $request->validate([
                'products' => 'required|array',
                'purchase_id' => 'nullable|integer',
                'date' => 'nullable|date',
                'supplierId' => 'required|exists:suppliers,id',
                'totals' => 'required|array',
                'totals.subTotal' => 'required|numeric',
                'totals.tax' => 'nullable|numeric',
                'totals.discount' => 'nullable|numeric',
                'totals.shipping' => 'nullable|numeric',
                'totals.grandTotal' => 'required|numeric',
            ]);

            if ($validatedData['purchase_id'] == null) {
                DB::beginTransaction();
                // Step 2: Create a new purchase record
                try {
                    $purchase = Purchase::create([
                        'supplier_id' => $validatedData['supplierId'],
                        'user_id' => auth()->id(),
                        'business_id'   => $businessId,
                        'sub_total' => $validatedData['totals']['subTotal'],
                        'tax' => $validatedData['totals']['tax'],
                        'discount_value' => $validatedData['totals']['discount'],
                        'shipping' => $validatedData['totals']['shipping'],
                        'grand_total' => $validatedData['totals']['grandTotal'],
                        'date' => $validatedData['date'] ?? Carbon::now()->toDateString(),
                        'status' => 1,
                    ]);

                    // Step 3: Create purchase items
                    foreach ($validatedData['products'] as $product) {
                        $existingProduct = Product::findOrFail($product['id']);
                        PurchaseItem::create([
                            'purchase_id' => $purchase->id,
                            'product_id' => $product['id'],
                            'purchase_price' => $product['purchase_price'],
                            'price' => $product['price'],
                            'quantity' => $product['qty'],
                            'remaining_qty' => $product['qty'],
                        ]);

                        $existingProduct->increment('quantity', $product['qty']);
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json(['error' => $e->getMessage()], 400);
                }
            } else {
                DB::beginTransaction();
                try {
                    $purchase = Purchase::findOrFail($validatedData['purchase_id']);
                    $purchase->update([
                        'supplier_id' => $validatedData['supplierId'],
                        'user_id' => auth()->id(),
                        'business_id'   => $businessId,
                        'sub_total' => $validatedData['totals']['subTotal'],
                        'tax' => $validatedData['totals']['tax'],
                        'discount_value' => $validatedData['totals']['discount'],
                        'shipping' => $validatedData['totals']['shipping'],
                        'grand_total' => $validatedData['totals']['grandTotal'],
                        'date' => $validatedData['date'] ?? Carbon::now()->toDateString(),
                        'status' => 1,
                    ]);
                    // Step 3: Create purchase items
                    foreach ($validatedData['products'] as $product) {
                        $existingProduct = Product::findOrFail($product['id']); 
                        // Find the existing purchase item, if any, and get its quantity or set to 0
                        $oldPurchaseItem = PurchaseItem::find($product['item_id']??0);
                        $oldQuantity = $oldPurchaseItem ? $oldPurchaseItem->quantity : 0;
                        $remainingQty = $oldPurchaseItem 
                            ? ($oldPurchaseItem->remaining_qty + ($product['qty'] - $oldQuantity))
                            : $product['qty'];

                        PurchaseItem::updateOrCreate(
                            [
                                'id' => $product['item_id'] ?? null
                            ],
                            [
                                'purchase_id' => $purchase->id,
                                'product_id' => $product['id'],
                                'purchase_price' => $product['purchase_price'],
                                'price' => $product['price'],
                                'quantity' => $product['qty'],
                                'remaining_qty' => $remainingQty,
                            ]
                        );

                        // Adjust product stock: first add back the old quantity, then subtract the new
                        $existingProduct->decrement('quantity', $oldQuantity);
                        $existingProduct->increment('quantity', $product['qty']);
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json(['error' => $e->getMessage()], 400);
                }
            }
            // Step 4: Return a response
            return response()->json([
                'message' => 'Purchase saved successfully.',
                'purchase' => $purchase,
            ], 201);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        if ($request->wantsJson()) {
            $businessId = session('business_id');
            $purchase = Purchase::with('items', 'supplier')->findOrFail($id);
            
            return $purchase;
        }
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
                    PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $product->id,
                        'purchase_price' => $item['purchase_price'],
                        'price' => $item['price'],
                        'quantity' => $item['quantity'],
                        'remaining_qty' => $item['quantity'],
                    ]);
                    
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
