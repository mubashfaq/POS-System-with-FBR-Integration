<?php

namespace App\Http\Controllers\Backend\Pos;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\PosCart;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{

public function index(Request $request)
{
    if ($request->wantsJson()) {
        // Get customer registration status from session 
        $customerIsRegistered = session('customer_is_registered', 'Registered'); // ✅ Default "Registered" capital
        
        // $cartItems = PosCart::where('user_id', auth()->id())
        //     ->with('product')
        //     ->latest('created_at')
        //     ->get()
        //     ->map(function ($item) use ($customerIsRegistered) {
        //         // Calculate base total
        //         $baseTotal = round(($item->quantity * $item->product->discounted_price), 2);
                
        //         $furtherTax = 0;
        //         if ($item->product->sale_type === "3rd Schedule Goods" && 
        //             strtolower($customerIsRegistered) === "unregistered") { 
        //             $furtherTax = round($baseTotal * 0.04, 2);
        //         }
                
        //         // Calculate total with tax
        //         $totalWithTax = $baseTotal + $furtherTax;
                
        //         // Add calculated values to item
        //         $item->base_total = $baseTotal;
        //         $item->further_tax = $furtherTax;
        //         $item->row_total = $totalWithTax;
        //         $item->customer_is_registered = $customerIsRegistered;
                
        //         return $item;
        //     });

        $cartItems = PosCart::where('user_id', auth()->id())
            ->with('product')
            ->latest('created_at')
            ->get()
            ->map(function ($item) use ($customerIsRegistered) {
                $unitPrice = $item->price_override ?? $item->product->discounted_price;
                $baseTotal = round($unitPrice * $item->quantity, 2);
               

                $furtherTax = 0;
               $furtherTax = 0;
$valueExTax = $baseTotal;
$salesTax = 0;

if (
    $item->product->sale_type != "3rd Schedule Goods" &&
    strtolower($customerIsRegistered) === "unregistered"
) {
    $rateText = trim($item->product->rate_of_tax); // e.g "18%"

    if (str_ends_with($rateText, '%')) {

        $rate = floatval(str_replace('%', '', $rateText)); // 18

        // Price already includes GST
        $valueExTax = round($baseTotal / (1 + ($rate / 100)), 2);

        // Further tax = 4% of valueExTax
        $furtherTax = round($valueExTax * 0.04, 2);

        // Only for info (optional)
        $salesTax = round($baseTotal - $valueExTax, 2);

    } else {
        // If fixed tax (rare case)
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

                return $item;
        });

            
        $subTotal = $cartItems->sum('base_total');
        $furtherTaxTotal = $cartItems->sum('further_tax');
        $grandTotal = $cartItems->sum('row_total');

        
        return response()->json([
            'carts' => $cartItems,
            'sub_total' => round($subTotal, 2),
            'further_tax_total' => round($furtherTaxTotal, 2),
            'total' => round($grandTotal, 2)
        ]);
    }
    // clear cart
    PosCart::where('user_id', auth()->id())->delete();
    return view('backend.cart.index');
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
       
        // dd($businessId);
        $products = Product::query()
        ->where('business_id', $businessId) // ✅ filter current business
        ->active()
        ->stocked();


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

  
  public function store(Request $request)
{
    
    $request->validate([
        'id' => 'required|exists:products,id',
    ]);

    $product = Product::where('id', $request->id)
        ->where('business_id', session('business_id'))
        ->firstOrFail();

       

    // Check stock
    if ($product->quantity <= 0) {
        return response()->json(['message' => 'Insufficient stock'], 400);
    }

    $cartItem = PosCart::where('user_id', auth()->id())
        ->where('product_id', $product->id)
        ->first();

        

    $quantity = $cartItem ? $cartItem->quantity + 1 : 1;

    // ===================== FURTHER TAX CALCULATION =====================
    $unitPrice = $product->discounted_price;
    $baseTotal = $unitPrice * $quantity;

    $registrationStatus = strtolower(session('registration_status') ?? 'registered');

    $furtherTax = 0;
    if (strtolower(trim($product->sale_type)) != '3rd schedule goods' &&
        $registrationStatus === 'unregistered') {
        $rateText = trim($product->rate_of_tax);
        if (str_ends_with($rateText, '%')) {
            $rate = floatval(str_replace('%', '', $rateText));
            $valueExTax = $baseTotal / (1 + ($rate / 100));
            $furtherTax = round($valueExTax * 0.04, 2);
        } else {
            $furtherTax = floatval($rateText) ?? 0;
        }
    }
    // ===================================================================

    if ($cartItem) {
        $cartItem->quantity = $quantity;
        $cartItem->further_tax = $furtherTax;
        $cartItem->save();
    } else {
        PosCart::create([
            'user_id' => auth()->id(),
            'product_id' => $product->id,
            'quantity' => $quantity,
            'further_tax' => $furtherTax,
        ]);
    }

    return response()->json([
        'message' => 'Product added to cart',
        'further_tax' => $furtherTax,
    ], 201);
}

    public function increment(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:pos_carts,id'
        ]);

        $cart = PosCart::with('product')->findOrFail($request->id);
        if ($cart->product->quantity <= 0) {
            return response()->json(['message' => 'Insufficient stock available'], 400);
        }
        if ($cart->quantity == $cart->product->quantity) {
            return response()->json(['message' => 'Cannot add more, stock limit reached'], 400);
        }
        $cart->quantity = $cart->quantity + 1;
        $cart->save();
        return response()->json(['message' => 'Cart Updated successfully'], 200);
    }
    public function decrement(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:pos_carts,id'
        ]);
        $cart = PosCart::findOrFail($request->id);
        if ($cart->quantity <= 1) {
            return response()->json(['message' => 'Quantity cannot be less than 1.'], 400);
        }
        $cart->quantity = $cart->quantity - 1;
        $cart->save();
        return response()->json(['message' => 'Cart Updated successfully'], 200);
    }
    public function delete(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:pos_carts,id'
        ]);

        $cart = PosCart::findOrFail($request->id);
        $cart->delete();

        return response()->json(['message' => 'Item successfully deleted'], 200);
    }
    public function empty()
    {
        $deletedCount = PosCart::where('user_id', auth()->id())->delete();

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



public function updateQuantity(Request $request)
{
    $request->validate([
        'id' => 'required|exists:pos_carts,id',
        'quantity' => 'required|integer|min:1',
    ]);

    $cart = PosCart::with('product')->findOrFail($request->id);

    // Product ka stock
    $availableStock = $cart->product->quantity ?? 0;
    if ($request->quantity > $availableStock) {
        return response()->json(['message' => 'Cannot add more, stock limit reached'], 400);
    }

    $cart->quantity = $request->quantity;

    // Further Tax calculation backend me
    $product = $cart->product;
    $baseTotal = $cart->quantity * $product->discounted_price;
    $furtherTax = 0;

    // session me registration_status save karlo frontend se ya default
    $registrationStatus = strtolower(session('registration_status') ?? 'registered');

    if (strtolower(trim($product->sale_type)) != '3rd schedule goods' &&
        $registrationStatus === 'unregistered'
    ) {
        $rateText = trim($product->rate_of_tax);
        if (str_ends_with($rateText, '%')) {
            $rate = floatval(str_replace('%', '', $rateText));
            $valueExTax = $baseTotal / (1 + ($rate / 100));
            $furtherTax = $valueExTax * 0.04; // 4% further tax
        } else {
            $furtherTax = floatval($rateText) ?? 0;
        }
    }

    $cart->further_tax = round($furtherTax, 2);
    $cart->row_total = round($baseTotal + $cart->further_tax, 2);
    $cart->save();

    return response()->json([
        'message' => 'Quantity updated successfully',
        'quantity' => $cart->quantity,
        'row_total' => $cart->row_total,
        'further_tax' => $cart->further_tax,
    ], 200);
}

    public function updatePrice(Request $request)
{
    $request->validate([
        'id' => 'required|exists:pos_carts,id',
        'price' => 'required|numeric|min:0'
    ]);

    $cart = PosCart::with('product')->findOrFail($request->id);

    $cart->price_override = $request->price;
    $cart->save();

    // Base total
    $price = $cart->price_override ?? $cart->product->discounted_price;
    $cart->row_total = round($price * $cart->quantity, 2);

    return response()->json([
        'message' => 'Price updated successfully',
        'price' => $price,
        'row_total' => $cart->row_total
    ], 200);
}

public function digitalInvoice(Request $request)
{
    if ($request->wantsJson()) {
        // Get customer registration status from session 
        $customerIsRegistered = session('customer_is_registered', 'Registered'); // ✅ Default "Registered" capital
        
        // $cartItems = PosCart::where('user_id', auth()->id())
        //     ->with('product')
        //     ->latest('created_at')
        //     ->get()
        //     ->map(function ($item) use ($customerIsRegistered) {
        //         // Calculate base total
        //         $baseTotal = round(($item->quantity * $item->product->discounted_price), 2);
                
        //         $furtherTax = 0;
        //         if ($item->product->sale_type === "3rd Schedule Goods" && 
        //             strtolower($customerIsRegistered) === "unregistered") { 
        //             $furtherTax = round($baseTotal * 0.04, 2);
        //         }
                
        //         // Calculate total with tax
        //         $totalWithTax = $baseTotal + $furtherTax;
                
        //         // Add calculated values to item
        //         $item->base_total = $baseTotal;
        //         $item->further_tax = $furtherTax;
        //         $item->row_total = $totalWithTax;
        //         $item->customer_is_registered = $customerIsRegistered;
                
        //         return $item;
        //     });

        $cartItems = PosCart::where('user_id', auth()->id())
            ->with('product')
            ->latest('created_at')
            ->get()
            ->map(function ($item) use ($customerIsRegistered) {
                $unitPrice = $item->price_override ?? $item->product->discounted_price;
                $baseTotal = round($unitPrice * $item->quantity, 2);
               

                $furtherTax = 0;
               $furtherTax = 0;
$valueExTax = $baseTotal;
$salesTax = 0;

if (
    $item->product->sale_type != "3rd Schedule Goods" &&
    strtolower($customerIsRegistered) === "unregistered"
) {
    $rateText = trim($item->product->rate_of_tax); // e.g "18%"

    if (str_ends_with($rateText, '%')) {

        $rate = floatval(str_replace('%', '', $rateText)); // 18

        // Price already includes GST
        $valueExTax = round($baseTotal / (1 + ($rate / 100)), 2);

        // Further tax = 4% of valueExTax
        $furtherTax = round($valueExTax * 0.04, 2);

        // Only for info (optional)
        $salesTax = round($baseTotal - $valueExTax, 2);

    } else {
        // If fixed tax (rare case)
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

                return $item;
        });

            
        $subTotal = $cartItems->sum('base_total');
        $furtherTaxTotal = $cartItems->sum('further_tax');
        $grandTotal = $cartItems->sum('row_total');

        
        return response()->json([
            'carts' => $cartItems,
            'sub_total' => round($subTotal, 2),
            'further_tax_total' => round($furtherTaxTotal, 2),
            'total' => round($grandTotal, 2)
        ]);
    }
    // clear cart
    PosCart::where('user_id', auth()->id())->delete();
    return view('backend.cart.digital-invoice');

}

}