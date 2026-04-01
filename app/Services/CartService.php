<?php

namespace App\Services;

use App\Models\PosCart;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\quantity_validation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CartService
{

    public function addToCart($productId, $businessId, $registrationStatus, $purchaseItemId = null)
    {
        $businessId = session('business_id') ? session('business_id') : $businessId;


        $quantityVal = quantity_validation::where('business_id' , $businessId)->first();
        
        if($quantityVal->product_quantity_validation == "disabled"){

            $product = Product::where('id', $productId)
                ->where('business_id', $businessId)
                ->firstOrFail();
            if ($product->quantity === 0) {
            

                throw new \Exception('Insufficient stock');
            }

            // If a specific purchase batch is selected, validate it
            $purchaseItem = null;
            if ($purchaseItemId) {
                $purchaseItem = PurchaseItem::where('id', $purchaseItemId)
                    ->where('product_id', $productId)
                    ->where('remaining_qty', '>', 0)
                    ->firstOrFail();
            }
        }else{
                $product = Product::where('id', $productId)
                ->where('business_id', $businessId)
                ->firstOrFail();
            // if ($product->quantity < 0) {
            
            //     throw new \Exception('Insufficient stock');
            // }

            // If a specific purchase batch is selected, validate it
            $purchaseItem = null;
            if ($purchaseItemId) {
                $purchaseItem = PurchaseItem::where('id', $purchaseItemId)
                    ->where('product_id', $productId)
                    ->firstOrFail();
            }
        }

        // Check if the same product+batch combo is already in cart
        $cartQuery = PosCart::where('user_id', Auth::id())
            ->where('product_id', $product->id);

        if ($purchaseItemId) {
            $cartQuery->where('purchase_item_id', $purchaseItemId);
        } else {
            $cartQuery->whereNull('purchase_item_id');
        }

        $cartItem = $cartQuery->first();
        $newQuantity = $cartItem ? $cartItem->quantity + 1 : 1;

        // Stock check: if batch selected, check batch stock; otherwise check product stock
        $quantityVal = quantity_validation::where('business_id' , $businessId)->first();

        if($quantityVal->product_quantity_validation == "disabled"){
            if ($purchaseItem) {
                if ($newQuantity > $purchaseItem->remaining_qty) {
                    throw new \Exception('Cannot add more, batch stock limit reached (Available: ' . $purchaseItem->remaining_qty . ')');
                }
            } else {
                if ($newQuantity > $product->quantity) {
                    throw new \Exception('Cannot add more, stock limit reached');
                }
            }
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
                'purchase_item_id' => $purchaseItemId,
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

    public function increment($id, $businessId)
    {
        $businessId = session('business_id') ? session('business_id') : $businessId;

        

        // Check batch stock limit if batch is selected
         $quantityVal = quantity_validation::where('business_id' , $businessId)->first();
        
        if($quantityVal->product_quantity_validation == "disabled"){
            $cart = PosCart::with('product')->findOrFail($id);

            if ($cart->product->quantity === 0) {
                throw new \Exception('Insufficient stock available');
            }

            if ($cart->purchase_item_id) {
                $purchaseItem = PurchaseItem::findOrFail($cart->purchase_item_id);
                if ($cart->quantity >= $purchaseItem->remaining_qty) {
                    throw new \Exception('Cannot add more, batch stock limit reached (Available: ' . $purchaseItem->remaining_qty . ')');
                }
            } else {
                if ($cart->quantity == $cart->product->quantity) {
                    throw new \Exception('Cannot add more, stock limit reached');
                }
            }
        }else{

         $cart = PosCart::with('product')->findOrFail($id);

          
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

    public function updateQuantity($id, $quantity, $registrationStatus, $businessId)
    {
        $businessId = session('business_id') ? session('business_id') : $businessId;
        $cart = PosCart::with('product')->findOrFail($id);

        // Stock check: batch-aware
         $quantityVal = quantity_validation::where('business_id' , $businessId)->first();
        
        if($quantityVal->product_quantity_validation == "disabled"){
            if ($cart->purchase_item_id) {
                $purchaseItem = PurchaseItem::findOrFail($cart->purchase_item_id);
                if ($quantity > $purchaseItem->remaining_qty) {
                    throw new \Exception('Cannot add more, batch stock limit reached (Available: ' . $purchaseItem->remaining_qty . ')');
                }
            } else {
                $availableStock = $cart->product->quantity ?? 0;
                if ($quantity > $availableStock) {
                    throw new \Exception('Cannot add more, stock limit reached');
                }
            }
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

        $cart->price_override = $price;

        $finalPrice = $price ?? $cart->product->discounted_price;

        $cart->row_total = round($finalPrice * $cart->quantity, 2);

        $cart->save();

        return $cart;
    }
}
