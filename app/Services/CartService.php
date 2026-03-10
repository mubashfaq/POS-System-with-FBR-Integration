<?php

namespace App\Services;

use App\Models\PosCart;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;


class CartService
{

    public function addToCart($productId, $businessId, $registrationStatus )
    {
        $product = Product::where('id', $productId)
            ->where('business_id', $businessId)
            ->firstOrFail();

        if ($product->quantity <= 0) {
            throw new \Exception('Insufficient stock');
        }

        $cartItem = PosCart::where('user_id', Auth::id())
            ->where('product_id', $product->id)
            ->first();

        $newQuantity = $cartItem ? $cartItem->quantity + 1 : 1;

        if ($newQuantity > $product->quantity) {
            throw new \Exception('Cannot add more, stock limit reached');
        }

        // Base calculation
        $unitPrice = $product->discounted_price;
        $baseTotal = $unitPrice * $newQuantity;

        $furtherTax = $this->calculateFurtherTax(
            $product,
            $baseTotal,
            $registrationStatus
        );

        if ($cartItem) {
            $cartItem->quantity = $newQuantity;
            $cartItem->further_tax = $furtherTax;
            $cartItem->save();
        } else {
            $cartItem = PosCart::create([
                'user_id' => Auth::id(),
                'product_id' => $product->id,
                'quantity' => $newQuantity,
                'further_tax' => $furtherTax,
            ]);
        }

        return $cartItem;
    }

    private function calculateFurtherTax($product, $baseTotal, $registrationStatus)
    {
        $registrationStatus = strtolower($registrationStatus);
        $furtherTax = 0;

        if (
            strtolower(trim($product->sale_type)) != '3rd schedule goods' &&
            $registrationStatus === 'unregistered'
        ) {
            $rateText = trim($product->rate_of_tax);

            if (str_ends_with($rateText, '%')) {
                $rate = floatval(str_replace('%', '', $rateText));
                $valueExTax = $baseTotal / (1 + ($rate / 100));
                $furtherTax = round($valueExTax * 0.04, 2);
            } else {
                $furtherTax = floatval($rateText) ?? 0;
            }
        }

        return $furtherTax;
    }

    public function increment($id)
    {
        $cart = PosCart::with('product')->findOrFail($id);
        if ($cart->product->quantity <= 0) {
            throw new \Exception('Insufficient stock available');
        }
        if ($cart->quantity == $cart->product->quantity) {
            throw new \Exception('Cannot add more, stock limit reached');
        }
        $cart->quantity = $cart->quantity + 1;
        $cart->save();

        return $cart;
    }

    public function decrement($id)
    {
        $cart = PosCart::findOrFail($id);
        if ($cart->quantity <= 1) {
            throw new \Exception('Quantity cannot be less than 1.');
        }
        $cart->quantity = $cart->quantity - 1;
        $cart->save();

        return $cart;
    }

    public function delete($id)
    {
        $cart = PosCart::findOrFail($id);
        $cart->delete();

        return $cart;
    }

    public function clearCart()
    {
        $deletedCount = PosCart::where('user_id', auth()->id())->delete();

        return $deletedCount;
    }

    public function updateQuantity($id, $quantity, $registrationStatus)
    {
        $cart = PosCart::with('product')->findOrFail($id);

        // Ownership protection
        // if ($cart->user_id !== Auth::id()) {
        //     throw new \Exception('Unauthorized action');
        // }

        $availableStock = $cart->product->quantity ?? 0;

        if ($quantity > $availableStock) {
            throw new \Exception('Cannot add more, stock limit reached');
        }

        $cart->quantity = $quantity;

        $product = $cart->product;
        $baseTotal = $quantity * $product->discounted_price;
        $furtherTax = 0;

        $registrationStatus = strtolower($registrationStatus);

        if (
            strtolower(trim($product->sale_type)) != '3rd schedule goods' &&
            $registrationStatus === 'unregistered'
        ) {
            $rateText = trim($product->rate_of_tax);

            if (str_ends_with($rateText, '%')) {
                $rate = floatval(str_replace('%', '', $rateText));
                $valueExTax = $baseTotal / (1 + ($rate / 100));
                $furtherTax = $valueExTax * 0.04;
            } else {
                $furtherTax = floatval($rateText) ?? 0;
            }
        }

        $cart->further_tax = round($furtherTax, 2);
        $cart->row_total = round($baseTotal + $cart->further_tax, 2);
        $cart->save();

        return $cart;
    }

    public function updatePrice($id, $price)
    {
        $cart = PosCart::with('product')->findOrFail($id);

        // if ($cart->user_id !== Auth::id()) {
        //     throw new \Exception('Unauthorized action');
        // }

        $cart->price_override = $price;

        $finalPrice = $price ?? $cart->product->discounted_price;

        $cart->row_total = round($finalPrice * $cart->quantity, 2);

        $cart->save();

        return $cart;
    }
}
