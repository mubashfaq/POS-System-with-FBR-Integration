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
                'product_id' => (int) ($cart->product_id ?? $cart->product?->id),
                'packing_id' => $cart->packing_id ? (int) $cart->packing_id : null,
                'qty_unit_id' => $cart->qty_unit_id ? (int) $cart->qty_unit_id : null,
                'entered_qty' => round((float) ($cart->entered_qty ?? $cart->quantity), 4),
                'qty' => round((float) $cart->quantity, 4),
            ];
        })
        ->sortBy(fn($item) => $item['product_id'] . '-' . ($item['packing_id'] ?? 0) . '-' . ($item['qty_unit_id'] ?? 0))
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
                        'product_id' => (int) $product->product_id,
                        'packing_id' => $product->packing_id ? (int) $product->packing_id : null,
                        'qty_unit_id' => $product->qty_unit_id ? (int) $product->qty_unit_id : null,
                        'entered_qty' => round((float) ($product->entered_qty ?? $product->quantity), 4),
                        'qty' => round((float) $product->quantity, 4),
                    ];
                })
                ->sortBy(fn($item) => $item['product_id'] . '-' . ($item['packing_id'] ?? 0) . '-' . ($item['qty_unit_id'] ?? 0))
                ->values();

            if ($cartItems->toJson() === $orderItems->toJson()) {
                return $order;
            }
        }

        return null;
    }
}
