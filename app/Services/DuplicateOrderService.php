<?php

namespace App\Services;

use App\Models\Order;

class DuplicateOrderService
{
    /**
     * Check if an identical order already exists.
     *
     * @param int $customerId
     * @param int $businessId
     * @param \Illuminate\Support\Collection $carts
     * @return \App\Models\Order|null
     */
    public function findDuplicate($customerId, $businessId, $carts)
    {
        // Current cart items
        $cartItems = $carts->map(function ($cart) {
            return [
                'product_id' => $cart->product->id,
                'qty' => (float) $cart->quantity,
            ];
        })
        ->sortBy('product_id')
        ->values();

        // Orders from last 1 day
        $orders = Order::with('products')
            ->where('customer_id', $customerId)
            ->where('business_id', $businessId)
            ->where('is_cancelled', 0)
            ->where('created_at', '>=', now()->subDay())
            ->get();

        foreach ($orders as $order) {

            $orderItems = $order->products
                ->map(function ($product) {
                    return [
                        'product_id' => $product->product_id,
                        'qty' => (float) $product->quantity,
                    ];
                })
                ->sortBy('product_id')
                ->values();

            if ($cartItems->toJson() === $orderItems->toJson()) {
                return $order;
            }
        }

        return null;
    }
}