<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Imports\ProductsImport;
use App\Models\Brand;
use App\Models\Business;
use App\Models\Category;
use App\Models\HsCode;
use App\Models\Product;
use App\Models\ProductLog;
use App\Models\quantity_validation;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\SaleType;
use App\Models\SroItemCode;
use App\Models\Uom;
use App\Models\Unit;
use App\Models\Packing;
use App\Models\ProductUnitPrice;
use App\Services\ProductLogService;
use App\Services\ProductVariationService;
use App\Services\StockReconciliationService;
use App\Trait\FileHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\DataTables;
use App\Services\FbrTokenService;
use Carbon\Carbon;
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
            $products = Product::with(['unit', 'variations' => fn($q) => $q->withSum('purchaseItems as stock_sum', 'remaining_qty')])
                ->withSum('purchaseItems as stock_sum', 'remaining_qty')->where('business_id', $businessId)->latest()->get();

            return DataTables::of($products)
                ->addIndexColumn()
                ->addColumn('image', fn($data) => '<img src="' . asset('storage/' . $data->image) . '" loading="lazy" alt="' . $data->name . '" class="img-thumb img-fluid" onerror="this.onerror=null; this.src=\'' . asset('assets/images/no-image.png') . '\';" height="80" width="60" />')
                ->addColumn('name', fn($d) => e($d->name) . ($d->has_variations
                    ? '<br><small class="text-muted">' . $d->variations->count() . ' variations</small>' : ''))
                ->addColumn(
                    'price',
                    fn($data) => $data->discounted_price .
                        ($data->price > $data->discounted_price
                            ? '<br><del>' . $data->price . '</del>'
                            : '')
                )
                ->addColumn('quantity', fn($d) => $d->quantity . ' ' . optional($d->unit)->short_name)
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
                      <a class="dropdown-item" href="' . route('backend.admin.products.edit', $data->id) . '">
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
            $term = $request->search;

            $products = Product::with([
                'unit',
                'defaultPacking.levels.unit',
                'unitPrices',
                'variations' => fn($q) => $q
                    ->withSum('purchaseItems as stock_sum', 'remaining_qty')->with('values')
            ])
                ->where('business_id', $businessId)
                ->where(fn($q) => $q
                    ->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('sku', $term)
                    ->orWhereHas('variations', fn($v) => $v->where('sku', $term)))
                ->get();
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

        $businessId = session('business_id');

        $business_type = Business::where('id', $businessId)->value('business_type');

        $brands = Brand::where('status', true)->where(function ($query) use ($businessId) {
            $query->where('business_id', $businessId);
        })->get();
        $categories = Category::where('status', true)->where(function ($query) use ($businessId) {
            $query->where('business_id', $businessId);
        })->get();

        $hsCodes = hsCode::all();
        $units = Uom::all();
        $saleTypes = SaleType::all();
        $sroItems = SroItemCode::all();

        // All products available as accessories
        $allProducts = Product::where('business_id', $businessId)->orderBy('name')->get(['id', 'name']);

        $fbrIntegration = FbrTokenService::isIntegrationEnabled();

        $baseUnits = Unit::where('is_active', true)->orderBy('title')->get();
        $packings = Packing::where('is_active', true)
            ->with(['baseUnit', 'levels.unit'])
            ->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId);
                if (!$businessId) {
                    $query->orWhereNull('business_id');
                }
            })
            ->get();

        return view('backend.products.create', compact(
            'brands',
            'categories',
            'units',
            'baseUnits',
            'packings',
            'hsCodes',
            'saleTypes',
            'sroItems',
            'business_type',
            'allProducts',
            'fbrIntegration'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request, ProductVariationService $variations)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('product_create'), 403);

        $validated = $request->validated();

        $businessId = session('business_id');
        $validated['business_id'] = $businessId;

        $openingQty = (float) ($validated['quantity'] ?? 0);

        $validated['quantity'] = 0;

        DB::beginTransaction();

        try {

            $product = Product::create($validated);

            if ($request->hasFile('product_image')) {
                $product->image = $this->fileHandler->fileUploadAndGetPath($request->file('product_image'), '/public/media/products');
                $product->save();
            }

            if ($request->has('accessories')) {

                $accessoryData = [];

                foreach ($request->accessories as $accId => $qty) {

                    // Prevent product from being its own accessory
                    if ((int) $accId !== $product->id) {
                        $accessoryData[$accId] = ['quantity' => max(1, (float) $qty)];
                    }
                }

                $product->accessories()->sync($accessoryData);

            } else {
                $product->accessories()->detach();
            }

            if ($request->has('unit_prices')) {
                $product->unitPrices()->delete();
                if (is_array($request->unit_prices)) {
                    foreach ($request->unit_prices as $up) {
                        if (!empty($up['unit_id']) && isset($up['sale_price']) && $up['sale_price'] !== '') {
                            $product->unitPrices()->create([
                                'unit_id' => $up['unit_id'],
                                'sale_price' => (float) $up['sale_price'],
                            ]);
                        }
                    }
                }
            }

            $hasVariations = $request->boolean('has_variations');

            if ($hasVariations) {

                $units = collect($request->input('variations', []))->map(function ($row) use ($variations, $product, $businessId) {

                    $variation = $variations->create($product, $row, $businessId);

                    return [
                        'variation' => $variation,
                        'purchase_price' => (float) $product->purchase_price,
                        'price' => (float) $row['price'],
                        'qty' => (float) ($row['quantity'] ?? 0),
                    ];
                });

            } else {
                $units = collect([
                    [
                        'variation' => null,
                        'purchase_price' => (float) ($product->purchase_price ?? 0),
                        'price' => (float) ($validated['price'] ?? 0),
                        'qty' => $openingQty,
                    ]
                ]);
            }

            $variations->postOpeningBatch($product, $units, $validated['supplier_id'] ?? null, $businessId);

            DB::commit();

            ProductLogService::log(ProductLog::ACTION_CREATED, $product, null, ProductLogService::snapshot($product->fresh()));

            return redirect()->route('backend.admin.products.index')->with('success', 'Product created successfully!');

        } catch (\Exception $e) {

            DB::rollBack();

            return redirect()->back()->withInput()->with('error', 'Product creation failed: ' . $e->getMessage());
        }
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

        $product = Product::with([
            'accessories',
            'unitPrices',
            'defaultPacking.levels.unit',
            'variations' => fn($q) => $q
                ->withSum('purchaseItems as stock_sum', 'remaining_qty')
                ->with('values.attribute'),
        ])->findOrFail($id);
        $brands = Brand::where('status', true)->where(function ($query) use ($businessId) {
            $query->where('business_id', $businessId);
        })->get();
        $categories = Category::where('status', true)->where(function ($query) use ($businessId) {
            $query->where('business_id', $businessId);
        })->get();
        $units = Uom::all();
        $hsCodes = hsCode::all();
        $saleTypes = SaleType::all();
        $sroItems = SroItemCode::all();

        // All products available as accessories (exclude self)
        $allProducts = Product::where('business_id', $businessId)->where('id', '!=', $id)->orderBy('name')->get(['id', 'name']);

        // Currently selected accessories: [product_id => quantity]
        $selectedAccessories = $product->accessories->mapWithKeys(function ($acc) {
            return [$acc->id => $acc->pivot->quantity];
        });

        $fbrIntegration = FbrTokenService::isIntegrationEnabled();

        $baseUnits = Unit::where('is_active', true)->orderBy('title')->get();
        $packings = Packing::where('is_active', true)
            ->with(['baseUnit', 'levels.unit'])
            ->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId);
                if (!$businessId) {
                    $query->orWhereNull('business_id');
                }
            })
            ->get();

        return view('backend.products.edit', compact(
            'brands',
            'categories',
            'units',
            'baseUnits',
            'packings',
            'product',
            'hsCodes',
            'saleTypes',
            'sroItems',
            'business_type',
            'allProducts',
            'selectedAccessories',
            'fbrIntegration'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, $id, ProductVariationService $variations)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('product_update'), 403);

        $businessId = session('business_id');
        $validated = $request->validated();

        $product = Product::where('business_id', $businessId)->findOrFail($id);
        $oldImage = $product->image;

        unset($validated['quantity'], $validated['has_variations'], $validated['variations']);

        $imageReplaced = false;
        $oldSnapshot = ProductLogService::snapshot($product);   // ← before changes

        DB::beginTransaction();

        try {
            $product->update($validated);

            if ($request->hasFile('product_image')) {
                $product->image = $this->fileHandler->fileUploadAndGetPath($request->file('product_image'), '/public/media/products');
                $product->save();
                $imageReplaced = true;
            }

            // Accessories
            if ($request->has('accessories')) {
                $accessoryData = [];
                foreach ($request->accessories as $accId => $qty) {
                    if ((int) $accId !== $product->id) {
                        $accessoryData[$accId] = ['quantity' => max(1, (float) $qty)];
                    }
                }
                $product->accessories()->sync($accessoryData);
            } else {
                $product->accessories()->detach();
            }

            if ($request->has('unit_prices')) {
        // dd('unit_price');
                $product->unitPrices()->delete();
                if (is_array($request->unit_prices)) {
                    foreach ($request->unit_prices as $up) {
                        if (!empty($up['unit_id']) && isset($up['sale_price']) && $up['sale_price'] !== '') {
                            $product->unitPrices()->create([
                                'unit_id' => $up['unit_id'],
                                'sale_price' => (float) $up['sale_price'],
                            ]);
                        }
                    }
                }
            }

            // Variations
            if ($product->has_variations) {
                $newUnits = $variations->sync($product, $request->input('variations', []), $businessId);

                if ($newUnits->isNotEmpty()) {
                    $variations->postOpeningBatch($product, $newUnits, $validated['supplier_id'] ?? null, $businessId);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()->withInput()->with('error', 'Product update failed: ' . $e->getMessage());
        }

        // Log after commit — snapshot fresh state
        ProductLogService::log(ProductLog::ACTION_UPDATED, $product, $oldSnapshot, ProductLogService::snapshot($product->fresh()));

        if ($imageReplaced) {
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

        $product = Product::findOrFail($id);
        $snapshot = ProductLogService::snapshot($product);

        ProductLogService::log(ProductLog::ACTION_DELETED, $product, $snapshot, null);

        if ($product->image != '') {
            $this->fileHandler->secureUnlink($product->image);
        }

        // Soft-delete all variations first (FK is now restrict, not cascade)
        $product->variations()->delete();

        $product->delete();

        return redirect()->back()->with('success', 'Product Deleted Successfully');
    }

    public function import(Request $request)
    {
        if ($request->boolean('download-demo')) {
            $currentBusiness = currentUserBusiness();
            abort_if(!$currentBusiness || !$currentBusiness->hasPermission('product_view'), 403);

            $path = 'samples/stock_import_sample.xlsx';

            if (!Storage::disk('local')->exists($path)) {
                abort(404, 'Sample file not found.');
            }

            return Storage::disk('local')->download(
                $path,
                'demo_products.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            );
        }

        if ($request->isMethod('post') && $request->hasFile('file')) {

            $file = $request->file('file');
            $businessId = session('business_id');

            if (!$businessId) {
                return redirect()->route('backend.admin.products.index')->with('error', 'No active business selected.');
            }

            $processedPath = null;
            $isTemp = false;

            try {
                [$processedPath, $isTemp] = $this->convertExcelFormulasToValues($file);

                // Sanity: file must be readable and non-empty
                if (!is_string($processedPath) || !is_file($processedPath) || filesize($processedPath) === 0) {
                    throw new \RuntimeException('Processed import file is empty or missing.');
                }

                // Force XLSX reader regardless of filename extension
                $readerType = \Maatwebsite\Excel\Excel::XLSX;
                if (strtolower(pathinfo($processedPath, PATHINFO_EXTENSION)) === 'xls') {
                    $readerType = \Maatwebsite\Excel\Excel::XLS;
                } elseif (strtolower(pathinfo($processedPath, PATHINFO_EXTENSION)) === 'csv') {
                    $readerType = \Maatwebsite\Excel\Excel::CSV;
                }

                $import = new ProductsImport($businessId);
                Excel::import($import, $processedPath, null, $readerType);
                $result = $import->result;
                $message = sprintf(
                    'Products imported. New: %d, Duplicates skipped: %d, Ignored rows: %d',
                    $result['imported'],
                    $result['duplicates'],
                    $result['skipped'],
                );

                return redirect()->route('backend.admin.products.index')->with('success', $message);

            } catch (\Throwable $e) {
                Log::error('❌ Import failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                return redirect()->route('backend.admin.products.index')->with('error', 'Import failed: ' . $e->getMessage());

            } finally {
                if ($isTemp && $processedPath && file_exists($processedPath)) {
                    @unlink($processedPath);
                }
            }
        }

        return view('backend.products.import');
    }

    /**
     * @return array{0:string,1:bool} [path, isTemporary]
     */
    private function convertExcelFormulasToValues($uploadedFile): array
    {
        $originalPath = $uploadedFile->getRealPath();

        // Always create a .xlsx temp path, even when no formulas
        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_' . time() . '_' . uniqid() . '.xlsx';

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($originalPath);
            $worksheet = $spreadsheet->getActiveSheet();

            $highestRow = (int) $worksheet->getHighestRow();
            $dataStart = 9; // rows before this are headers

            // Detect formulas only if there is data
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
                // Copy as-is; extension now .xlsx so reader detection works
                if (!@copy($originalPath, $tempFile)) {
                    throw new \RuntimeException('Could not copy upload to temp file.');
                }
                return [$tempFile, true];
            }

            // --- Rebuild spreadsheet with calculated values ---
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

                    // Preserve HS CODE (column B) trailing zeros
                    if ($col === 2 && $row >= $dataStart) {
                        $newWorksheet->setCellValueExplicit(
                            $coord,
                            (string) $value,
                            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                        );
                    } else {
                        $newWorksheet->setCellValue($coord, $value);
                    }

                    try {
                        $newWorksheet->getCell($coord)->getStyle()->applyFromArray($cell->getStyle()->exportArray());
                    } catch (\Throwable $e) {
                        // style copy failure is non-fatal
                    }
                }
            }

            foreach ($worksheet->getColumnDimensions() as $c => $dim) {
                $newWorksheet->getColumnDimension($c)->setWidth($dim->getWidth());
            }
            foreach ($worksheet->getRowDimensions() as $r => $dim) {
                $newWorksheet->getRowDimension($r)->setRowHeight($dim->getRowHeight());
            }

            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($newSpreadsheet))->save($tempFile);

            return [$tempFile, true];

        } catch (\Throwable $e) {
            Log::warning('⚠️ Could not convert formulas: ' . $e->getMessage());

            // Guarantee a .xlsx-extension fallback so Maatwebsite can still read it
            if (!is_file($tempFile)) {
                @copy($originalPath, $tempFile);
            }
            return [$tempFile, true]; // treat as temp so it gets cleaned up
        }
    }

    public function checkUOMUsingHsCode()
    {
        // $token = env('FBR_TOKEN');
        $tokens = FbrTokenService::getToken();
        $token = is_array($tokens) ? $tokens['digital_token'] : $tokens;

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
        $tokens = FbrTokenService::getToken();
        $token = is_array($tokens) ? $tokens['digital_token'] : $tokens;
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
        $tokens = FbrTokenService::getToken();
        $token = is_array($tokens) ? $tokens['digital_token'] : $tokens;
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
        $tokens = FbrTokenService::getToken();
        $token = is_array($tokens) ? $tokens['digital_token'] : $tokens;
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
        $tokens = FbrTokenService::getToken();
        $token = is_array($tokens) ? $tokens['digital_token'] : $tokens;

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
        $tokens = FbrTokenService::getToken();
        $token = is_array($tokens) ? $tokens['digital_token'] : $tokens;

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

        $hs = HsCode::where("hs_code", $hs_code)->first();

        if ($hs) {

            //    $token = env('FBR_TOKEN');
            $tokens = FbrTokenService::getToken();
            $token = is_array($tokens) ? $tokens['digital_token'] : $tokens;

            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer $token",
                    'Content-Type' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Accept' => 'application/json'
                ])->timeout(30)->get('https://gw.fbr.gov.pk/pdi/v2/HS_UOM?hs_code=' . $hs_code . '&annexure_id=3');

                $uom = json_decode($response->body(), true);

            } catch (\Exception $e) {
                dd('Exception: ' . $e->getMessage());
            }




            return response()->json(['description' => $hs->item_desc, 'uom' => $uom]);
        } else {
            return response()->json(['description' => 'Description not found'], 404);
        }
    }

    public function getSaleTypeId($sale_type)
    {

        $st = SaleType::where("transactioN_DESC", $sale_type)->first();

        $saleTypeId = intval($st->transactioN_TYPE_ID);

        // $token = env('FBR_TOKEN');
        $tokens = FbrTokenService::getToken();
        $token = is_array($tokens) ? $tokens['digital_token'] : $tokens;

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept' => 'application/json'
            ])->timeout(60)->get('https://gw.fbr.gov.pk/pdi/v2/SaleTypeToRate?date=24-Feb2024&transTypeId=' . $saleTypeId . '&originationSupplier=1');


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
        $tokens = FbrTokenService::getToken();
        $token = is_array($tokens) ? $tokens['digital_token'] : $tokens;

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept' => 'application/json'
            ])->timeout(60)->get('https://gw.fbr.gov.pk/pdi/v1/SroSchedule?rate_id=' . $rate_tax_id . '&date=04-Feb2024&origination_supplier_csv=1');


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
        $tokens = FbrTokenService::getToken();
        $token = is_array($tokens) ? $tokens['digital_token'] : $tokens;
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept' => 'application/json'
            ])->timeout(60)->get('https://gw.fbr.gov.pk/pdi/v2/SROItem?date=2025-03-25&sro_id=' . $sro_schedule_id);


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

    public function getFormData()
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('product_create'), 403);

        $businessId = session('business_id');
        $business_type = $currentBusiness->business->business_type
            ?? Business::where('id', $businessId)->value('business_type');

        $units = Cache::remember('form_data.units', now()->addHours(6), function () {
            return Uom::select('id', 'description')->get();
        });

        $hsCodes = Cache::remember('form_data.hs_codes', now()->addHours(6), function () {
            return HsCode::select('id', 'hs_code')->get();
        });

        $saleTypes = Cache::remember('form_data.sale_types', now()->addHours(6), function () {
            return SaleType::select('id', 'transactioN_DESC')->get();
        });

        $fbrIntegration = FbrTokenService::isIntegrationEnabled();

        return response()->json(compact('units', 'hsCodes', 'saleTypes', 'business_type', 'fbrIntegration'));
    }

    public function storeAjax(StoreProductRequest $request)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('product_create'), 403);

        $validated = $request->validated();

        $businessId = session('business_id');

        if (!$businessId) {
            return response()->json([
                'status' => false,
                'message' => 'No business selected.'
            ], 400);
        }

        $validated['business_id'] = $businessId;

        // ✅ Auto-generate SKU if not provided
        if (empty($validated['sku'])) {
            $validated['sku'] = $this->generateUniqueSku($validated['name']);
        }

        $openingQty = (float) ($validated['quantity'] ?? 0);
        $validated['quantity'] = 0;

        DB::beginTransaction();

        try {

            $product = Product::create($validated);

            if ($request->hasFile('product_image')) {
                $product->image = $this->fileHandler->fileUploadAndGetPath($request->file('product_image'), '/public/media/products');
                $product->save();
            }

            $purchasePrice = (float) ($validated['purchase_price'] ?? 0);
            $price = (float) ($validated['price'] ?? 0);

            $purchase = Purchase::create([
                'supplier_id' => $validated['supplier_id'] ?? 0,
                'user_id' => auth()->id(),
                'business_id' => $businessId,
                'sub_total' => $purchasePrice * $openingQty,
                'tax' => 0,
                'discount_value' => 0,
                'shipping' => 0,
                'grand_total' => $purchasePrice * $openingQty,
                'date' => Carbon::now()->toDateString(),
                'status' => 1,
            ]);

            $purchaseItem = PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'purchase_price' => $purchasePrice,
                'price' => $price,
                'quantity' => $openingQty,
                'remaining_qty' => $openingQty,
            ]);

            StockReconciliationService::reconcileNewBatch($purchaseItem);

            if ($openingQty != 0) {
                $product->increment('quantity', $openingQty);
            }

            DB::commit();

            ProductLogService::log(ProductLog::ACTION_CREATED, $product, null, ProductLogService::snapshot($product->fresh()));

            return response()->json([
                'status' => true,
                'message' => 'Product created successfully!',
                'data' => $product->fresh()
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Product creation failed: ' . $e->getMessage()
            ], 400);
        }
    }

    private function generateUniqueSku(string $name): string
    {
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name)); // strip spaces & special chars
        $base = substr($base, 0, 12);                                   // max 12 chars from name
        $sku = $base . '-' . time();                                   // e.g. APPLEJUICE-1743300000

        if (Product::where('sku', $sku)->exists()) {
            $sku = $base . '-' . str_replace('.', '', microtime(true)); // e.g. APPLEJUICE-17433000001234
        }

        return $sku;
    }

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

        return response()->json([
            'success' => true,
            'status' => $request->status,
            'message' => $request->status === 'enabled' ? 'Stock validation disabled successfully.'
                : 'Stock validation enabled successfully.'
        ]);
    }
}