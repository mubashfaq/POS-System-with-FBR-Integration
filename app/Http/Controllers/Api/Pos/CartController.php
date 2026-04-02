<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function store(Request $request, CartService $cartService)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'purchase_item_id' => 'nullable|integer|exists:purchase_items,id'
        ]);

        $business = $request->attributes->get('business');

        try {

            $cartItem = $cartService->addToCart(
                $request->product_id,
                $business->id,
                currentRegistrationStatus(),
                $request->purchase_item_id
            );

            return response()->json([
                'message' => 'Product added to cart',
                'data' => $cartItem
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function increment(Request $request, $id, CartService $cartService)
    {
        $business = $request->attributes->get('business');

        try {

            $cart = $cartService->increment($id, $business->id);

            return response()->json([
                'message' => 'Cart updated',
                'data' => $cart
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function decrement($id, CartService $cartService)
    {
        try {

            $cart = $cartService->decrement($id);

            return response()->json([
                'message' => 'Cart updated',
                'data' => $cart
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function updateQuantity(Request $request, CartService $cartService)
    {
        $request->validate([
            'cart_id' => 'required|exists:pos_carts,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $business = $request->attributes->get('business');

        try {

            $cart = $cartService->updateQuantity(
                $request->cart_id,
                $request->quantity,
                currentRegistrationStatus(),
                $business->id
            );

            return response()->json([
                'message' => 'Quantity updated',
                'data' => $cart
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
            'cart_id' => 'required|exists:pos_carts,id',
            'price' => 'required|numeric|min:0'
        ]);

        try {

            $cart = $cartService->updatePrice(
                $request->cart_id,
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

    public function destroy($id, CartService $cartService)
    {
        try {

            $cartService->delete($id);

            return response()->json([
                'message' => 'Item deleted'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function empty(CartService $cartService)
    {
        $cartService->clearCart();

        return response()->json([
            'message' => 'Cart cleared'
        ]);
    }
}
