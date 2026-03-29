<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Trait\FileHandler;
use App\Http\Resources\ProductResource;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public $fileHandler;

    public function __construct(FileHandler $fileHandler)
    {
        $this->fileHandler = $fileHandler;
    }

    /**
     * Display a listing of the products.
     */
    public function index(Request $request)
    {
        $business = $request->attributes->get('business');

        if (!$business) {
            return response()->json(['message' => 'Business context required.'], 400);
        }

        $query = Product::where('business_id', $business->id);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', $search);
            });
        }

        $products = $query->latest()->get();

        return response()->json([
            'message' => 'Products retrieved successfully',
            'data' => ProductResource::collection($products)
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request)
    {
        $business = $request->attributes->get('business');

        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business context required.'
            ], 400);
        }

        $validated = $request->validated();
        $validated['business_id'] = $business->id;

        $product = Product::create($validated);
        
        if ($request->hasFile("product_image")) {
            $product->image = $this->fileHandler->fileUploadAndGetPath($request->file("product_image"), "/public/media/products");
            $product->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Product created successfully!',
            'data' => new ProductResource($product)
        ], 201);
    }

    /**
     * Display the specified product.
     */
    public function show(Request $request, $id)
    {
        $business = $request->attributes->get('business');
        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business context required.'
            ], 400);
        }

        $product = Product::where('business_id', $business->id)->findOrFail($id);

        return response()->json([
            'message' => 'Product retrieved successfully',
            'data' => new ProductResource($product)
        ], 200);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, $id)
    {
        $business = $request->attributes->get('business');

        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business context required.'
            ], 400);
        }

        $product = Product::where('business_id', $business->id)->findOrFail($id);

        $validated = $request->validated();
        $oldImage = $product->image;
        $product->update($validated);

        if ($request->hasFile("product_image")) {
            $product->image = $this->fileHandler->fileUploadAndGetPath($request->file("product_image"), "/public/media/products");
            $product->save();
            
            if ($oldImage) {
                $this->fileHandler->secureUnlink($oldImage);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Product updated successfully!',
            'data' => new ProductResource($product)
        ], 200);
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Request $request, $id)
    {
        $business = $request->attributes->get('business');
        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business context required.'
            ], 400);
        }

        $product = Product::where('business_id', $business->id)->findOrFail($id);

        if ($product->image != '') {
            $this->fileHandler->secureUnlink($product->image);
        }
        
        $product->delete();

        return response()->json([
            'status' => true,
            'message' => 'Product deleted successfully',
        ], 200);
    }
}
