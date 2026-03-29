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
use App\Models\Business;
use App\Models\Category;
use App\Models\HsCode;
use App\Models\Product;
use App\Models\quantity_validation;
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
use App\Services\FbrTokenService;
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
            $products = $products->where('business_id', $businessId)->get();
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

        $business_type = Business::where('id', $businessId)->value('business_type');

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
        

        return view('backend.products.create', compact('brands', 'categories', 'units' , 'hsCodes' , 'saleTypes', 'sroItems', 'business_type'));
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

        $businessId = session('business_id');

        $business_type = Business::where('id', $businessId)->value('business_type');

        // abort_if(!auth()->user()->can('product_update'), 403);

        $product = Product::findOrFail($id);
        $brands = Brand::whereStatus(true)->get();
        $categories = Category::whereStatus(true)->get();
        $units = Unit::all();
         $hsCodes = hsCode::all();
        $units = Uom::all();
        $saleTypes = SaleType::all();
        $sroItems = SroItemCode::all();
        return view('backend.products.edit', compact('brands', 'categories', 'units', 'product', 'hsCodes', 'saleTypes', 'sroItems', 'business_type'));
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
    // public function import(Request $request)
    // {
    //     if ($request->query('download-demo')) {
    //         return Excel::download(new DemoProductsExport, 'demo_products.xlsx');
    //     }
    //     if ($request->isMethod('post') && $request->hasFile('file')) {
    //         Excel::import(new ProductsImport, $request->file('file'));
    //         return redirect()->back()->with('success', 'Products imported successfully.');
    //     }
    //     return view('backend.products.import');
    // }

    public function import(Request $request)
    {
        if ($request->query('download-demo')) {
            return Excel::download(new DemoProductsExport, 'demo_products.xlsx');
        }
        if ($request->isMethod('post') && $request->hasFile('file')) {
            
            $file = $request->file('file');
            $businessId = session('business_id'); 
            
            try {
                // Convert formulas to values before importing
                $processedFile = $this->convertExcelFormulasToValues($file);
                
                // Import using the processed file
                Excel::import(new ProductsImport($businessId), $processedFile);
                
                // Clean up temporary file if it's different from original
                if ($processedFile !== $file->getRealPath() && file_exists($processedFile)) {
                    unlink($processedFile);
                }
                
                return redirect()->back()->with('success', 'Products imported successfully.');
                
            } catch (\Exception $e) {
                Log::error('❌ Import failed: ' . $e->getMessage());
                return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage());
            }
        }

        return view('backend.products.import');
    }

    /**
     * Convert Excel formulas to their calculated values
     * This handles system-generated Excel files with formulas automatically
     */
    private function convertExcelFormulasToValues($uploadedFile)
    {
        try {
            // Load the Excel file
            $inputFileName = $uploadedFile->getRealPath();
            
            // Try to load with PhpSpreadsheet
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Check if file has formulas
            $hasFormulas = false;
            $cellE9 = $worksheet->getCell('E9')->getValue();
            
            if (is_string($cellE9) && strpos($cellE9, '=') === 0) {
                $hasFormulas = true;
            }
            
            if (!$hasFormulas) {
                return $inputFileName;
            }
            
            // Create a new spreadsheet for output
            $newSpreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $newWorksheet = $newSpreadsheet->getActiveSheet();
            
            // Copy all cell values (formulas will be evaluated)
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
            
            for ($row = 1; $row <= $highestRow; $row++) {
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
                    $cell = $worksheet->getCell($cellCoordinate);
                    
                    // Get calculated value (this will evaluate formulas)
                    try {
                        $value = $cell->getCalculatedValue();
                    } catch (\Exception $e) {
                        // If formula can't be calculated, use the raw value
                        $value = $cell->getValue();
                    }
                    
                    // Special handling for column B (HS CODE) to preserve trailing zeros
                    if ($col === 2 && $row >= 9) { // Column B, data rows
                        // Force as string to preserve trailing zeros like 8544.4290
                        $value = strval($value);
                        $newWorksheet->setCellValueExplicit(
                            $cellCoordinate, 
                            $value, 
                            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                        );
                    } else {
                        // Set the value in new worksheet
                        $newWorksheet->setCellValue($cellCoordinate, $value);
                    }
                    
                    // Copy formatting
                    $newWorksheet->getCell($cellCoordinate)->getStyle()
                        ->applyFromArray($cell->getStyle()->exportArray());
                }
            }
            
            // Copy column widths
            foreach ($worksheet->getColumnDimensions() as $col => $dimension) {
                $newWorksheet->getColumnDimension($col)->setWidth($dimension->getWidth());
            }
            
            // Copy row heights
            foreach ($worksheet->getRowDimensions() as $row => $dimension) {
                $newWorksheet->getRowDimension($row)->setRowHeight($dimension->getRowHeight());
            }
            
            // Save to temporary file
            $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_' . time() . '_' . uniqid() . '.xlsx';
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($newSpreadsheet);
            $writer->save($tempFile);
                        
            return $tempFile;
            
        } catch (\Exception $e) {
            Log::warning('⚠️ Could not convert formulas: ' . $e->getMessage());
            return $uploadedFile->getRealPath();
        }
    }

    public function checkUOMUsingHsCode(){
        // $token = env('FBR_TOKEN');
        $token = FbrTokenService::getToken();

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
        // $token = env('FBR_TOKEN');
        $token = FbrTokenService::getToken();
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
        // $token = env('FBR_TOKEN');
        $token = FbrTokenService::getToken();
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
        // $token = env('FBR_TOKEN');
        $token = FbrTokenService::getToken();
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
        // $token = env('FBR_TOKEN');
        $token = FbrTokenService::getToken();

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
        // $token = env('FBR_TOKEN');
        $token = FbrTokenService::getToken();

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

        //    $token = env('FBR_TOKEN');
        $token = FbrTokenService::getToken();

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
       
        // $token = env('FBR_TOKEN');
        $token = FbrTokenService::getToken();

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

       
        // $token = env('FBR_TOKEN');
        $token = FbrTokenService::getToken();

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

       
        // $token = env('FBR_TOKEN');  
        $token = FbrTokenService::getToken();
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

    // public function updateStockSetting(Request $request)
    // {
    //      $businessId = session('business_id');
    //     $request->validate([
    //         'status' => 'required|in:enabled,disabled'
    //     ]);

    //     quantity_validation::query()->where('busniess_id' , $businessId)->update([
    //         'product_quantity_validation' => $request->status
    //     ]);

    //     return response()->json(['success' => true]);
    // }

    public function updateStockSetting(Request $request)
{
    $businessId = session('business_id');

    $request->validate([
        'status' => 'required|in:enabled,disabled'
    ]);

    quantity_validation::updateOrInsert(
        ['business_id' => $businessId], // condition
        ['product_quantity_validation' => $request->status] // values to insert/update
    );

    return response()->json(['success' => true]);
}
}