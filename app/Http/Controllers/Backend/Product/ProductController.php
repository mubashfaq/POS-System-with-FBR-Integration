<?php

namespace App\Http\Controllers\Backend\Product;

use App\Exports\DemoProductsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\ImportStockRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Imports\ProductsImport;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HsCode;
use App\Models\Product;
use App\Models\SaleType;
use App\Models\SroItemCode;
use App\Models\Unit;
use App\Models\Uom;
use App\Trait\FileHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public $fileHandler;

    public function __construct(FileHandler $fileHandler)
    {
        $this->fileHandler = $fileHandler;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('product_view'), 403);

        // abort_if(!auth()->user()->can('product_view'), 403);        
        $businessId = session('business_id');
        if ($request->ajax()) {
            $products = Product::where('business_id', $businessId) // 👈 filter by business
            ->latest()
            ->get();
            return DataTables::of($products)
                ->addIndexColumn()
                ->addColumn('image', fn($data) => '<img src="' . asset('storage/' . $data->image) . '" loading="lazy" alt="' . $data->name . '" class="img-thumb img-fluid" onerror="this.onerror=null; this.src=\'' . asset('assets/images/no-image.png') . '\';" height="80" width="60" />')
                ->addColumn('name', fn($data) => $data->name)
                ->addColumn(
                    'price',
                    fn($data) => $data->discounted_price .
                        ($data->price > $data->discounted_price
                            ? '<br><del>' . $data->price . '</del>'
                            : '')
                )
                ->addColumn('quantity', fn($data) => $data->quantity . ' ' . optional($data->unit)->short_name)
                ->addColumn('created_at', fn($data) => $data->created_at->format('d M, Y'))
                ->addColumn('status', fn($data) => $data->status
                    ? '<span class="badge bg-primary">Active</span>'
                    : '<span class="badge bg-danger">Inactive</span>')
                ->addColumn('action', function ($data) {
                    return '<div class="btn-group">
                    <button type="button" class="btn btn-flat">Action</button>
                    <button type="button" class="btn btn-flat dropdown-toggle dropdown-icon" data-toggle="dropdown" aria-expanded="false">
                      <span class="sr-only">Toggle Dropdown</span>
                    </button>
                    <div class="dropdown-menu" role="menu">
                      <a class="dropdown-item" href="'.route('backend.admin.products.edit', $data->id). '">
                    <i class="fas fa-edit"></i> Edit
                </a> <div class="dropdown-divider"></div>
<form action="' . route('backend.admin.products.destroy', $data->id) . '"method="POST" style="display:inline;">
                   ' . csrf_field() . '
                    ' . method_field("DELETE") . '
<button type="submit" class="dropdown-item" onclick="return confirm(\'Are you sure ?\')"><i class="fas fa-trash"></i> Delete</button>
                  </form>
<div class="dropdown-divider"></div>
  <a class="dropdown-item" href="' . route('backend.admin.purchase.create', ['barcode' => $data->sku]) . '">
                <i class="fas fa-cart-plus"></i> Purchase
            </a>
                    </div>
                  </div>';
                })
                ->rawColumns(['image', 'name', 'price', 'quantity', 'status', 'created_at', 'action'])
                ->toJson();
        }
        if ($request->wantsJson()) {
            $request->validate([
                'search' => 'required|string|max:255',
            ]);

            // Initialize the query
            $products = Product::query();

            // Apply filters based on the search term
            $products = $products->where(function ($query) use ($request) {
                $query->where('name', 'LIKE', "%{$request->search}%")
                    ->orWhere('sku', $request->search);
            });
            // Get the results
            $products = $products->get();
            // Return the results as a JSON response
            return ProductResource::collection($products);
        }
        return view('backend.products.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('product_create'), 403);

        // abort_if(!auth()->user()->can('product_create'), 403);
        $businessId = session('business_id');

        // $brands = Brand::whereStatus(true)->get();
        $brands = Brand::where('status', true)
            ->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)
                    ->orWhereNull('business_id');
            })
            ->get();
        // $categories = Category::whereStatus(true)->get();
        $categories = Category::where('status', true)
            ->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)
                    ->orWhereNull('business_id');
            })
            ->get();

       

        $hsCodes = hsCode::all();
        $units = Uom::all();
        $saleTypes = SaleType::all();
        $sroItems = SroItemCode::all();

        // $units = Unit::all();

        // $UoM = $this->saveUoms();
       
        
        // $hsCodes = $this->saveHsCodes();
        // $this->saveSaleTypes();
        

        return view('backend.products.create', compact('brands', 'categories', 'units' , 'hsCodes' , 'saleTypes', 'sroItems'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('product_create'), 403);

        // abort_if(!auth()->user()->can('product_create'), 403);
        $validated = $request->validated();

        // $businessId = auth()->user()->businesses()->first()->id ?? null;
        // $businessId = Session::get('business_id');
        $businessId = session('business_id');

        $validated['business_id'] = $businessId;
        $product = Product::create($validated);
        if ($request->hasFile("product_image")) {
            $product->image = $this->fileHandler->fileUploadAndGetPath($request->file("product_image"), "/public/media/products");
            $product->save();
        }

        return redirect()->route('backend.admin.products.index')->with('success', 'Product created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('product_update'), 403);

        // abort_if(!auth()->user()->can('product_update'), 403);

        $product = Product::findOrFail($id);
        $brands = Brand::whereStatus(true)->get();
        $categories = Category::whereStatus(true)->get();
        $units = Unit::all();
         $hsCodes = hsCode::all();
        $units = Uom::all();
        $saleTypes = SaleType::all();
        $sroItems = SroItemCode::all();
        return view('backend.products.edit', compact('brands', 'categories', 'units', 'product', 'hsCodes', 'saleTypes', 'sroItems'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, $id)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('product_update'), 403);

        // abort_if(!auth()->user()->can('product_update'), 403);
        $validated = $request->validated();
        $product = Product::findOrFail($id);
        $oldImage = $product->image;
        $product->update($validated);
        if ($request->hasFile("product_image")) {
            $product->image = $this->fileHandler->fileUploadAndGetPath($request->file("product_image"), "/public/media/products");
            $product->save();
            $this->fileHandler->secureUnlink($oldImage);
        }

        return redirect()->route('backend.admin.products.index')->with('success', 'Product updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('product_delete'), 403);

        // abort_if(!auth()->user()->can('product_delete'), 403);
        $product = Product::findOrFail($id);
        if ($product->image != '') {
            $this->fileHandler->secureUnlink($product->image);
        }
        $product->delete();
        return redirect()->back()->with('success', 'Product Deleted Successfully');
    }

    public function importStockForm()
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('product_create'), 403);
        
        Log::info('Stock Import Form Accessed', [
            'business_id' => session('business_id'),
            'user_id' => auth()->id()
        ]);
        
        return view('backend.products.import-stock');
    }

    public function importStock(ImportStockRequest $request)
    {
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
            
            Log::info('File Data Read', [
                'total_rows' => count($data),
                'sample_rows' => array_slice($data, 0, 5)
            ]);
            
            $results = [
                'success' => [],
                'errors' => [],
                'total' => count($data),
                'updated' => 0,
                'failed' => 0
            ];
            
            foreach ($data as $index => $row) {
                $rowNumber = $index + 2;
                
                Log::info("Processing Row {$rowNumber}", [
                    'row_data' => $row,
                    'all_keys' => array_keys($row)
                ]);
                
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
                
                Log::info("Row {$rowNumber} Extracted Values", [
                    'slug' => $slug,
                    'hsCode' => $hsCode,
                    'quantity' => $quantity
                ]);
                
                // Validate required fields
                if (empty($slug) || empty($hsCode) || empty($quantity)) {
                    $error = "Row {$rowNumber}: ITEMS ('{$slug}'), HS CODE ('{$hsCode}'), and No. of CONT ('{$quantity}') are required.";
                    $results['errors'][] = $error;
                    $results['failed']++;
                    
                    Log::warning("Row {$rowNumber} Validation Failed", [
                        'slug' => $slug ?: 'missing',
                        'hscode' => $hsCode ?: 'missing',
                        'quantity' => $quantity ?: 'missing'
                    ]);
                    continue;
                }
                
                // Validate quantity
                if ($quantity <= 0) {
                    $error = "Row {$rowNumber}: No. of CONT must be greater than 0.";
                    $results['errors'][] = $error;
                    $results['failed']++;
                    
                    Log::warning("Row {$rowNumber} Invalid Quantity", ['quantity' => $quantity]);
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
                    
                    Log::info("Row {$rowNumber} Trying Normalized Match", [
                        'original_hscode' => $hsCode,
                        'normalized_hscode' => $hsCodeNormalized
                    ]);
                    
                    // Find products with matching slug and business
                    $candidateProducts = Product::where('slug', $slug)
                                            ->where('business_id', $businessId)
                                            ->get();
                    
                    // Try to match by normalized HS code
                    foreach ($candidateProducts as $candidate) {
                        $dbHsCodeNormalized = rtrim(rtrim($candidate->hsCode, '0'), '.');
                        
                        if ($dbHsCodeNormalized === $hsCodeNormalized) {
                            $product = $candidate;
                            Log::info("Row {$rowNumber} Matched via Normalization", [
                                'excel_hscode' => $hsCode,
                                'db_hscode' => $candidate->hsCode,
                                'normalized_match' => $hsCodeNormalized
                            ]);
                            break;
                        }
                    }
                }
                
                if (!$product) {
                    $error = "Row {$rowNumber}: Product with ITEMS '{$slug}' and HS CODE '{$hsCode}' not found.";
                    $results['errors'][] = $error;
                    $results['failed']++;
                    
                    Log::warning("Row {$rowNumber} Product Not Found", [
                        'slug' => $slug,
                        'hscode' => $hsCode,
                        'business_id' => $businessId
                    ]);
                    continue;
                }
                
                // Update stock (quantity column)
                $oldStock = $product->quantity ?? 0;
                $newStock = $oldStock + $quantity;
                $product->quantity = $newStock;
                $product->save();
                
                $success = "Row {$rowNumber}: Product '{$product->name}' (ITEMS: {$slug}, HS CODE: {$hsCode}) stock updated from {$oldStock} to {$newStock}.";
                $results['success'][] = $success;
                $results['updated']++;
                
                Log::info("Row {$rowNumber} Stock Updated", [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'slug' => $slug,
                    'hscode' => $hsCode,
                    'old_stock' => $oldStock,
                    'new_stock' => $newStock,
                    'added_quantity' => $quantity
                ]);
            }
            
            DB::commit();
            
            Log::info('Stock Import Completed', [
                'total' => $results['total'],
                'updated' => $results['updated'],
                'failed' => $results['failed']
            ]);
            
            // Prepare session message
            $message = "Stock import completed. ";
            $message .= "{$results['updated']} products updated successfully. ";
            
            if ($results['failed'] > 0) {
                $message .= "{$results['failed']} rows failed.";
                session()->flash('import_errors', array_slice($results['errors'], 0, 50));
                session()->flash('successes', $results['success']);
                return redirect()->route('backend.admin.products.index')
                            ->with('warning', $message);
            }
            
            session()->flash('successes', $results['success']);
            return redirect()->route('backend.admin.products.index')
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
        Log::info('Reading Excel File');
        
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
            
            Log::info('Excel All Rows Read', [
                'total_rows' => count($allRows),
                'first_10_rows' => array_slice($allRows, 0, 10)
            ]);
            
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
            
            Log::info('Excel Header Found', [
                'header_row_index' => $headerRowIndex,
                'header_row' => $allRows[$headerRowIndex]
            ]);
            
            // Get header and normalize
            $header = array_map('strtolower', array_map('trim', $allRows[$headerRowIndex]));
            
            Log::info('Excel Header Processed', ['header' => $header]);
            
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
            
            Log::info('Excel File Processing Complete', [
                'total_data_rows' => count($data),
                'sample_data' => array_slice($data, 0, 3)
            ]);
            
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
        Log::info('Reading CSV File');
        
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
            
            Log::info('CSV All Rows Read', [
                'total_rows' => count($allRows),
                'first_10_rows' => array_slice($allRows, 0, 10)
            ]);
            
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
            
            Log::info('CSV Header Found', [
                'header_row_index' => $headerRowIndex,
                'header' => $allRows[$headerRowIndex]
            ]);
            
            $header = array_map('strtolower', array_map('trim', $allRows[$headerRowIndex]));
            
            // Read data rows after header
            for ($i = $headerRowIndex + 1; $i < count($allRows); $i++) {
                $row = $allRows[$i];
                if (count($row) === count($header) && !empty(array_filter($row))) {
                    $data[] = array_combine($header, $row);
                }
            }
            
            Log::info('CSV File Processing Complete', [
                'total_data_rows' => count($data),
                'sample_data' => array_slice($data, 0, 3)
            ]);
            
            return $data;
            
        } catch (\Exception $e) {
            Log::error('CSV Reading Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function import(Request $request)
    {
        if ($request->query('download-demo')) {
            return Excel::download(new DemoProductsExport, 'demo_products.xlsx');
        }
        if ($request->isMethod('post') && $request->hasFile('file')) {
            Excel::import(new ProductsImport, $request->file('file'));
            return redirect()->back()->with('success', 'Products imported successfully.');
        }
        return view('backend.products.import');
    }

    public function checkUOMUsingHsCode(){
        $token = env('FBR_TOKEN');

        try {
            $response = Http::withHeaders([
               'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept' => 'application/json'
            ])->timeout(60)->get('https://gw.fbr.gov.pk/pdi/v2/HS_UOM?hs_code=5904.9000&annexure_id=3');

            // Show raw response
            // dd($response->body());

            // Optional: decode JSON if needed
            $data = json_decode($response->body(), true);

            dd($data);
            // dd($data);
             // Make sure JSON is valid
        if (json_last_error() !== JSON_ERROR_NONE) {
            return []; // return empty array if JSON is invalid
        }

        // return the data array
        return $data; 

        } catch (\Exception $e) {
            dd('Exception: ' . $e->getMessage());
        }
         
    }

    public function getFbrHscodes()
    {
        $token = env('FBR_TOKEN');
        try {
            $response = Http::withHeaders([
               'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept' => 'application/json'
            ])->timeout(60)->get('https://gw.fbr.gov.pk/pdi/v1/itemdesccode');

            // Show raw response
            // dd($response->body());

            // Optional: decode JSON if needed
            $data = json_decode($response->body(), true);
            // dd($data);
             // Make sure JSON is valid
        if (json_last_error() !== JSON_ERROR_NONE) {
            return []; // return empty array if JSON is invalid
        }

        // return the data array
        return $data; 

        } catch (\Exception $e) {
            dd('Exception: ' . $e->getMessage());
        }
    }

    public function getSroItem()
    {
        $token = env('FBR_TOKEN');
        try {
            $response = Http::withHeaders([
               'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept' => 'application/json'
            ])->timeout(60)->get('https://gw.fbr.gov.pk/pdi/v1/sroitemcode');

            // Show raw response
            // dd($response->body());

            // Optional: decode JSON if needed
            $data = json_decode($response->body(), true);
            // dd($data);
             // Make sure JSON is valid
        if (json_last_error() !== JSON_ERROR_NONE) {
            return []; // return empty array if JSON is invalid
        }

        // return the data array
        return $data; 

        } catch (\Exception $e) {
            dd('Exception: ' . $e->getMessage());
        }
    }


    public function getFbrSaleTypes()
    {
        $token = env('FBR_TOKEN');
        try {
            $response = Http::withHeaders([
               'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept' => 'application/json'
            ])->timeout(60)->get('https://gw.fbr.gov.pk/pdi/v1/transtypecode');

            // Show raw response
            // dd($response->body());

            // Optional: decode JSON if needed
            $data = json_decode($response->body(), true);
            // dd($data);
             // Make sure JSON is valid
        if (json_last_error() !== JSON_ERROR_NONE) {
            return []; // return empty array if JSON is invalid
        }

        // return the data array
        return $data; 

        } catch (\Exception $e) {
            dd('Exception: ' . $e->getMessage());
        }
    }

    public function saveHsCodes()
    {
        $hsCodes = $this->getFbrHscodes(); // API call
       
        if (empty($hsCodes)) {
            return "No data received!";
        }

        foreach ($hsCodes as $row) {

            HsCode::updateOrCreate(
                ['hs_code' => $row['hS_CODE']],   // unique field
                [
                    'item_desc' => $row['description'] ?? null,
                ]
            );
        }

        return "HS Codes saved successfully!";
    }

    // UOM
    public function getFbrUOM()
    {
        $token = env('FBR_TOKEN');

        try {
            $response = Http::withHeaders([
               'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept' => 'application/json'
            ])->timeout(60)->get('https://gw.fbr.gov.pk/pdi/v1/uom');

            // Show raw response
            // dd($response->body());

            // Optional: decode JSON if needed
            $data = json_decode($response->body(), true);
            // dd($data);
             // Make sure JSON is valid
        if (json_last_error() !== JSON_ERROR_NONE) {
            return []; // return empty array if JSON is invalid
        }

        // return the data array
        return $data; 

        } catch (\Exception $e) {
            dd('Exception: ' . $e->getMessage());
        }
    }

     public function getTransTypeCode()
    {
        $token = env('FBR_TOKEN');

        try {
            $response = Http::withHeaders([
               'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept' => 'application/json'
            ])->timeout(600)->get('https://gw.fbr.gov.pk/pdi/v1/transtypecode');

            // Show raw response
            // dd($response->body());

            // Optional: decode JSON if needed
            $data = json_decode($response->body(), true);
            dd($data);
            // dd($data);
             // Make sure JSON is valid
        if (json_last_error() !== JSON_ERROR_NONE) {
            return []; // return empty array if JSON is invalid
        }

        // return the data array
        return $data; 

        } catch (\Exception $e) {
            dd('Exception: ' . $e->getMessage());
        }
    }

     public function saveUoms()
    {
        $UoM = $this->getFbrUOM(); // API call
       
        if (empty($UoM)) {
            return "No data received!";
        }

        foreach ($UoM as $row) {

            Uom::updateOrCreate(
                ['uoM_ID' => $row['uoM_ID']],   // unique field
                [
                    'description' => $row['description'] ?? null,
                ]
            );
        }

        return "UOMs saved successfully!";
    }

    public function saveSroItem()
    {
        $sroItem = $this->getSroItem(); // API call
       
        if (empty($sroItem)) {
            return "No data received!";
        }

        foreach ($sroItem as $row) {

            SroItemCode::updateOrCreate(
                ['srO_ITEM_ID' => $row['srO_ITEM_ID']],   // unique field
                [
                    'srO_ITEM_DESC' => $row['srO_ITEM_DESC'] ?? null,
                ]
            );
        }

        return "Sro Codes saved successfully!";
    }

    public function saveSaleTypes()
    {
        $saleType = $this->getFbrSaleTypes(); // API call
       
        if (empty($saleType)) {
            return "No data received!";
        }

        foreach ($saleType as $row) {

            SaleType::updateOrCreate(
                ['transactioN_TYPE_ID' => $row['transactioN_TYPE_ID']],   // unique field
                [
                    'transactioN_DESC' => $row['transactioN_DESC'] ?? null,
                ]
            );
        }

        return "Sale Types saved successfully!";
    }

    public function getDescription($hs_code)
    {
        
        $hs = HsCode::where("hs_code",$hs_code)->first();

        if ($hs) {

           $token = env('FBR_TOKEN');

            try {
                $response = Http::withHeaders([
                'Authorization' => "Bearer $token",
                    'Content-Type' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Accept' => 'application/json'
                ])->timeout(30)->get('https://gw.fbr.gov.pk/pdi/v2/HS_UOM?hs_code='.$hs_code.'&annexure_id=3');

                $uom = json_decode($response->body(), true);

            } catch (\Exception $e) {
                dd('Exception: ' . $e->getMessage());
            }


            

            return response()->json(['description' => $hs->item_desc , 'uom' => $uom]);
        } else {
            return response()->json(['description' => 'Description not found'], 404);
        }
    }

    public function getSaleTypeId($sale_type)
    {
        
        $st = SaleType::where("transactioN_DESC",$sale_type)->first();
       
        $saleTypeId = intval($st->transactioN_TYPE_ID);
       
        $token = env('FBR_TOKEN');

        try {
            $response = Http::withHeaders([
               'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept' => 'application/json'
            ])->timeout(60)->get('https://gw.fbr.gov.pk/pdi/v2/SaleTypeToRate?date=24-Feb2024&transTypeId='.$saleTypeId.'&originationSupplier=1');

         
            $data = json_decode($response->body(), true);
            if ($data) {
                return response()->json(['sale_data' => $data]);
            } else {
                return response()->json(['sale_type_id' => 'Sale Type not found'], 404);
            }
            
        if (json_last_error() !== JSON_ERROR_NONE) {
            return []; // return empty array if JSON is invalid
        }

        // return the data array
        return $data; 

        } catch (\Exception $e) {
            dd('Exception: ' . $e->getMessage());
        }


    }

    public function getSroSchedule($rate_tax_id)
    {

       
        $token = env('FBR_TOKEN');

        try {
            $response = Http::withHeaders([
               'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept' => 'application/json'
            ])->timeout(60)->get('https://gw.fbr.gov.pk/pdi/v1/SroSchedule?rate_id='.$rate_tax_id.'&date=04-Feb2024&origination_supplier_csv=1');

         
            $data = json_decode($response->body(), true);
            if ($data) {
                return response()->json(['sro_data' => $data]);
            } else {
                return response()->json(['sro_item' => 'SRO Item not found'], 404);
            }
            
        if (json_last_error() !== JSON_ERROR_NONE) {
            return []; // return empty array if JSON is invalid
        }

        // return the data array
        return $data; 

        } catch (\Exception $e) {
            dd('Exception: ' . $e->getMessage());
        }
    }

    public function getSroItemDetail($sro_schedule_id)
    {

       
        $token = env('FBR_TOKEN');  
        try {
            $response = Http::withHeaders([
               'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept' => 'application/json'
            ])->timeout(60)->get('https://gw.fbr.gov.pk/pdi/v2/SROItem?date=2025-03-25&sro_id='.$sro_schedule_id);

         
            $data = json_decode($response->body(), true);

           
            if ($data) {
                return response()->json(['sro_data' => $data]);
            } else {
                return response()->json(['sro_item' => 'SRO Item not found'], 404);
            }
            
        if (json_last_error() !== JSON_ERROR_NONE) {
            return []; // return empty array if JSON is invalid
        }

        // return the data array
        return $data; 

        } catch (\Exception $e) {
            dd('Exception: ' . $e->getMessage());
        }
    }
}