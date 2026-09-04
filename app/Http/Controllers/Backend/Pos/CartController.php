<?php

namespace App\Http\Controllers\Backend\Pos;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Business;
use App\Models\CartSetting;
use App\Models\InvoiceSetting;
use App\Models\PosCart;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\quantity_validation;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{

    public function index(Request $request)
    {
        $businessId = session('business_id');
        $businesstype = Business::where('id', $businessId)->value('business_type');
        $cartSetting = CartSetting::where('business_id', $businessId)->value('draft_btn');
        // $invoiceSetting = InvoiceSetting::where('business_id', $businessId)->first(['invoice_number', 'imc_logo']);
        // $invoiceNumber = $invoiceSetting?->invoice_number;
        // $poNumber = $invoiceSetting?->imc_logo;

        if ($request->wantsJson()) {
            // Get customer registration status from session 
            $customerIsRegistered = session('customer_is_registered', 'Registered'); // ✅ Default "Registered" capital

            $cartItems = PosCart::where('user_id', auth()->id())
                ->with(['product', 'purchaseItem.purchase'])
                ->latest('created_at')
                ->get()
                ->map(function ($item) use ($customerIsRegistered) {
                    $unitPrice = $item->price_override ?? $item->product->discounted_price;
                    $baseTotal = round($unitPrice * $item->quantity, 2);

                    $furtherTax = 0;
                    $valueExTax = $baseTotal;
                    $salesTax = 0;

                    if (
                        $item->product->sale_type != "3rd Schedule Goods" &&
                        strtolower($customerIsRegistered) === "unregistered"
                    ) {
                        $rateText = trim($item->product->rate_of_tax);

                        if (str_ends_with($rateText, '%')) {
                            $rate = floatval(str_replace('%', '', $rateText));
                            $valueExTax = round($baseTotal / (1 + ($rate / 100)), 2);
                            $furtherTax = round($valueExTax * 0.04, 2);
                            $salesTax = round($baseTotal - $valueExTax, 2);
                        } else {
                            $fixedTax = floatval(preg_replace('/[^0-9.]/', '', $rateText));
                            $valueExTax = round($baseTotal - $fixedTax, 2);
                            $furtherTax = round($valueExTax * 0.04, 2);
                        }
                    }

                    $totalWithTax = $baseTotal + $furtherTax;

                    $item->unit_price              = $unitPrice;
                    $item->base_total              = $baseTotal;
                    $item->further_tax             = $furtherTax;
                    $item->row_total               = $totalWithTax;
                    $item->customer_is_registered  = $customerIsRegistered;
                    // Accessory flag for frontend grouping
                    $item->is_accessory            = !is_null($item->parent_cart_id);

                    return $item;
                });

            // Totals from parent items only (accessories don't contribute to price)
            $parentItems = $cartItems->filter(fn($i) => is_null($i->parent_cart_id));

            $subTotal        = $parentItems->sum('base_total');
            $furtherTaxTotal = $parentItems->sum('further_tax');
            $grandTotal      = $parentItems->sum('row_total');

            return response()->json([
                'carts'             => $cartItems->values(),
                'sub_total'         => round($subTotal, 2),
                'further_tax_total' => round($furtherTaxTotal, 2),
                'total'             => round($grandTotal, 2)
            ]);
        }
        // clear cart
        PosCart::where('user_id', auth()->id())->delete();
        return view('backend.cart.index', [
            'businessType' => $businesstype, 
            'draftButtonEnabled' => $cartSetting,
            // 'invoiceNumber' => $invoiceNumber,
            // 'poNumber' => $poNumber,
        ]);
    }

    // public function index(Request $request)
    // {
    //     if ($request->wantsJson()) {
    //         $cartItems = PosCart::where('user_id', auth()->id())
    //             ->with('product')
    //             ->latest('created_at')
    //             ->get()
    //             ->map(function ($item) {
    //                 $item->row_total = round(($item->quantity * $item->product->discounted_price),2);
    //                 return $item;
    //             });
    //         $total = $cartItems->sum('row_total');
    //         return response()->json([
    //             'carts' => $cartItems,
    //             'total' => round($total, 2)
    //         ]);
    //     }
    //     PosCart::where('user_id', auth()->id())->delete();
    //     return view('backend.cart.index');
    // }
    public function getProducts(Request $request)
    {
        $businessId = session('business_id');
       
         $quantityVal = quantity_validation::where('business_id' , $businessId)->first();
        
        if($quantityVal->product_quantity_validation == "disabled"){
             $products = Product::query()
        ->where('business_id', $businessId) // filter current business
        ->active()
        ->stocked();
        }else{
        // dd($businessId);
        $products = Product::query()
        ->where('business_id', $businessId);
        }


        // $products = Product::query()->active()->stocked();
        // Search by name if provided
        $products->when($request->search, function ($query, $search) {
            $query->where('name', 'LIKE', "%{$search}%");
        });

        // Search by barcode if provided
        $products->when($request->barcode, function ($query, $barcode) {
            $query->where('sku', $barcode);
        });
        $products = $products->latest()->paginate(96);

        if (request()->wantsJson()) {
            return ProductResource::collection($products);
        }
    }

    public function getQuantityValidation()
    {
        $businessId = session('business_id');
        $quantityVal = quantity_validation::where('business_id', $businessId)->firstOrFail();
        
        return response()->json([
            'quantity_validation' => $quantityVal->product_quantity_validation
        ]);
    }

    // public function store(Request $request)
    // {
    //     // Validate request input
    //     $request->validate([
    //         'id' => 'required|exists:products,id',
    //     ]);

    //     $product_id = $request->id;

    //     // Fetch the product
    //     // $product = Product::find($product_id);
    //     $product = Product::where('id', $product_id)
    //     ->where('business_id', session('business_id'))
    //     ->firstOrFail();

    //     // Check if the product is active and has sufficient stock
    //     if (!$product->status) {
    //         return response()->json(['message' => 'Product is not available'], 400);
    //     }

    //     if ($product->quantity <= 0) {
    //         return response()->json(['message' => 'Insufficient stock available'], 400);
    //     }

    //     // Fetch the cart item for the current user and product
    //     $cartItem = PosCart::where('user_id', auth()->id())->where('product_id', $product_id)->first();

    //     if ($cartItem) {
    //         // If the product is already in the cart, increment the quantity
    //         if ($cartItem->quantity < $product->quantity) {
    //             $cartItem->quantity += 1;
    //             $cartItem->save();
    //             return response()->json(['message' => 'Quantity updated', 'quantity' => $cartItem->quantity], 200);
    //         } else {
    //             return response()->json(['message' => 'Cannot add more, stock limit reached'], 400);
    //         }
    //     } else {
    //         // If not in the cart, create a new cart item
    //         $cart = new PosCart();
    //         $cart->user_id = auth()->id();
    //         $cart->product_id = $product_id;
    //         $cart->quantity = 1;
    //         $cart->save();
    //         return response()->json(['message' => 'Product added to cart', 'quantity' => 1], 201);
    //     }
    // }

  
    public function store(Request $request, CartService $cartService)
    {
        $request->validate([
            'id' => 'required|exists:products,id',
            'purchase_item_id' => 'nullable|integer|exists:purchase_items,id',
        ]);
    
        try {
    
            $cartItem = $cartService->addToCart(
                $request->id,
                session('business_id'),
                session('registration_status') ?? 'registered',
                $request->purchase_item_id
            );
    
            return response()->json([
                'message' => 'Product added to cart',
                'quantity' => $cartItem->quantity,
                'further_tax' => $cartItem->further_tax,
            ], 201);
    
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

     public function storeNewDigital(Request $request, CartService $cartService)
    {
        $request->validate([
            'id' => 'required|exists:products,id',
            'purchase_item_id' => 'nullable|integer|exists:purchase_items,id',
        ]);
    
        try {
    
            $cartItem = $cartService->addToCartNew(
                $request->id,
                session('business_id'),
                session('registration_status') ?? 'registered',
                $request->purchase_item_id
            );
    
            return response()->json([
                'message' => 'Product added to cart',
                'quantity' => $cartItem->quantity,
                'further_tax' => $cartItem->further_tax,
            ], 201);
    
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function increment(Request $request, CartService $cartService)
    {
        $request->validate([
            'id' => 'required|integer|exists:pos_carts,id'
        ]);

        try {
            $cart = $cartService->increment($request->id, session('business_id'));

            return response()->json(['message' => 'Cart Updated successfully'], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function decrement(Request $request, CartService $cartService)
    {
        $request->validate([
            'id' => 'required|integer|exists:pos_carts,id'
        ]);
       try {
            $cart = $cartService->decrement($request->id);
            return response()->json(['message' => 'Cart Updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }   
    }
    public function delete(Request $request, CartService $cartService)
    {
        $request->validate([
            'id' => 'required|integer|exists:pos_carts,id'
        ]);

        try {
            $cart = $cartService->delete($request->id);
            return response()->json(['message' => 'Item successfully deleted'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
        

    }
    public function empty(CartService $cartService)
    {
        $deletedCount = $cartService->clearCart();

        if ($deletedCount > 0) {
            return response()->json(['message' => 'Cart successfully cleared'], 200);
        }

        return response()->json(['message' => 'Cart is already empty'], 204);
    }

//    public function updateQuantity(Request $request)
// {
//     $request->validate([
//         'id' => 'required|exists:pos_carts,id',
//         'quantity' => 'required|integer|min:1',
//         'further_tax' => 'numeric|min:0',
//         'base_total' => 'numeric|min:0',
//         'row_total' => 'numeric|min:0'
//     ]);

//     $cart = PosCart::with('product')->findOrFail($request->id);

//     // Stock check
//     $availableStock = $cart->product->quantity ?? 0;
//     if ($request->quantity > $availableStock) {
//         return response()->json(['message' => 'Cannot add more, stock limit reached'], 400);
//     }

//     // Save all calculated fields
//     $cart->quantity = $request->quantity;
//     $cart->further_tax = $request->further_tax;
//     $cart->base_total = $request->base_total;
//     $cart->row_total = $request->row_total;
//     $cart->save();

//     return response()->json([
//         'message'   => 'Quantity updated successfully',
//         'quantity'  => $cart->quantity,
//         'base_total'=> $cart->base_total,
//         'row_total' => $cart->row_total,
//         'further_tax' => $cart->further_tax,
//     ], 200);
// }



public function updateQuantity(Request $request, CartService $cartService)
{
    $request->validate([
        'id' => 'required|exists:pos_carts,id',
        'quantity' => 'required',
    ]);

    try {

        $registrationStatus = session('registration_status') ?? 'registered';

        $cart = $cartService->updateQuantity(
            $request->id,
            $request->quantity,
            $registrationStatus,
            session('business_id')
        );

        return response()->json([
            'message' => 'Quantity updated successfully',
            'quantity' => $cart->quantity,
            'row_total' => $cart->row_total,
            'further_tax' => $cart->further_tax,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'message' => $e->getMessage()
        ], 400);
    }
}

public function updatePrice(Request $request, CartService $cartService)
{
    $request->validate([
        'id' => 'required|exists:pos_carts,id',
        'price' => 'required|numeric|min:0'
    ]);

    try {

        $cart = $cartService->updatePrice(
            $request->id,
            $request->price
        );

        return response()->json([
            'message' => 'Price updated successfully',
            'price' => $cart->price_override,
            'row_total' => $cart->row_total
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'message' => $e->getMessage()
        ], 400);
    }
}

public function digitalInvoice(Request $request)
    {
        if ($request->wantsJson()) {
            // Get customer registration status from session 
            $customerIsRegistered = session('customer_is_registered', 'Registered'); // ✅ Default "Registered" capital

            $cartItems = PosCart::where('user_id', auth()->id())
                ->with(['product', 'purchaseItem.purchase'])
                ->oldest('created_at')
                ->get()
                ->map(function ($item) use ($customerIsRegistered) {
                    $unitPrice = $item->price_override ?? $item->product->discounted_price;
                    $baseTotal = round($unitPrice * $item->quantity, 2);

                    $furtherTax = 0;
                    $valueExTax = $baseTotal;
                    $salesTax = 0;

                    if (
                        $item->product->sale_type != "3rd Schedule Goods" &&
                        strtolower($customerIsRegistered) === "unregistered"
                    ) {
                        $rateText = trim($item->product->rate_of_tax);

                        if (str_ends_with($rateText, '%')) {
                            $rate = floatval(str_replace('%', '', $rateText));
                            $valueExTax = round($baseTotal / (1 + ($rate / 100)), 2);
                            $furtherTax = round($valueExTax * 0.04, 2);
                            $salesTax = round($baseTotal - $valueExTax, 2);
                        } else {
                            $fixedTax = floatval(preg_replace('/[^0-9.]/', '', $rateText));
                            $valueExTax = round($baseTotal - $fixedTax, 2);
                            $furtherTax = round($valueExTax * 0.04, 2);
                        }
                    }

                    $totalWithTax = $baseTotal + $furtherTax;

                    $item->unit_price = $unitPrice;
                    $item->base_total = $baseTotal;
                    $item->further_tax = $furtherTax;
                    $item->row_total = $totalWithTax;
                    $item->customer_is_registered = $customerIsRegistered;
                    // Accessory flag for frontend grouping
                    $item->is_accessory = !is_null($item->parent_cart_id);

                    return $item;
                });

            // Totals from parent items only
            $parentItems = $cartItems->filter(fn($i) => is_null($i->parent_cart_id));

            $subTotal = $parentItems->sum('base_total');
            $furtherTaxTotal = $parentItems->sum('further_tax');
            $grandTotal = $parentItems->sum('row_total');

            return response()->json([
                'carts' => $cartItems->values(),
                'sub_total' => round($subTotal, 2),
                'further_tax_total' => round($furtherTaxTotal, 2),
                'total' => round($grandTotal, 2)
            ]);
        }
        $businessId = session('business_id');
        $cartSetting = CartSetting::where('business_id', $businessId)->first(['draft_btn', 'custom_field_btn']);
        $draftBtn = $cartSetting?->draft_btn;
        $CustomFieldBtn = $cartSetting?->custom_field_btn;
        $invoiceSetting = InvoiceSetting::where('business_id', $businessId)->first(['invoice_number', 'po_number']);
        $invoiceNumber = $invoiceSetting?->invoice_number;
        $poNumber = $invoiceSetting?->po_number;

        // clear cart
        PosCart::where('user_id', auth()->id())->delete();

        if ($request->route()->getName() === 'backend.admin.cart.digitalInvoiceNew') {
            return view('backend.cart.digital-invoice-new', [
                'draftButtonEnabled' => $draftBtn,
                'customFieldButton' => $CustomFieldBtn,
                'invoiceNumber' => $invoiceNumber,
                'poNumber' => $poNumber,
            ]);
        }
        return view('backend.cart.digital-invoice', [
            'draftButtonEnabled' => $draftBtn,
            'invoiceNumber' => $invoiceNumber,
            'poNumber' => $poNumber,
        ]);

    }

    /**
     * Get available purchase batches for a product.
     */
    public function getBatches($productId)
    {
        $businessId = session('business_id');

        $product = Product::where('id', $productId)
            ->where('business_id', $businessId)
            ->firstOrFail();

        $quantityVal = quantity_validation::where('business_id' , $businessId)->first();

        if($quantityVal->product_quantity_validation == "disabled"){

        $batches = PurchaseItem::where('product_id', $productId)
            ->where('remaining_qty', '>', 0)
            ->with('purchase:id,date,supplier_id')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'purchase_id' => $batch->purchase_id,
                    'purchase_price' => $batch->purchase_price,
                    'price' => $batch->price,
                    'remaining_qty' => $batch->remaining_qty,
                    'quantity' => $batch->quantity,
                    'purchase_date' => $batch->purchase 
                        ? \Carbon\Carbon::parse($batch->purchase->date)->format('Y-m-d') 
                        : $batch->created_at->format('Y-m-d'),
                    'created_at' => $batch->created_at,
                ];
            });

        } else {
                $batches = PurchaseItem::where('product_id', $productId)
                    ->with('purchase:id,date,supplier_id')
                    ->orderBy('created_at', 'desc')
                    ->get();

                // Map batches
                $batches = $batches->map(function ($batch) {
                    return [
                        'id' => $batch->id,
                        'purchase_id' => $batch->purchase_id, // original purchase_id
                        'purchase_price' => $batch->purchase_price,
                        'price' => $batch->price,
                        // 'remaining_qty' => $batch->remaining_qty > 0 ? $batch->remaining_qty : 1,
                        'remaining_qty' => $batch->remaining_qty,
                        'quantity' => $batch->quantity > 0 ? $batch->quantity : 1,
                        'purchase_date' => $batch->purchase 
                            ? \Carbon\Carbon::parse($batch->purchase->date)->format('Y-m-d') 
                            : $batch->created_at->format('Y-m-d'),
                        'created_at' => $batch->created_at,
                    ];
                });
    
        }
        return response()->json([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'total_stock' => $product->quantity,
            'batches' => $batches,
            'is_batch_enabled' => \App\Models\Business::find($businessId)->enable_batch ?? true,
        ]);
    }

    public function updateCustomFields(Request $request)
{
    $request->validate(['id' => 'required', 'custom_fields' => 'nullable|array']);
    $cart = PosCart::findOrFail($request->id);
    $cart->custom_fields = array_merge($cart->custom_fields ?? [], $request->custom_fields ?? []);
    $cart->save();
    return response()->json(['message' => 'Saved', 'cart' => $cart]);
}

}