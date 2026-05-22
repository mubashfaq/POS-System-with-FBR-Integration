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

    public function addToCart($productId, $businessId, $registrationStatus, $purchaseItemId = null, $parentCartId = null, $overrideQty = null, $skipStockCheck = false)
    {
        $businessId = session('business_id') ?: $businessId;


        $quantityVal = quantity_validation::where('business_id', $businessId)->firstOrFail();
        $isValidationDisabled = $quantityVal->product_quantity_validation === 'disabled';

        // ✅ Single product query — was duplicated in both if/else branches
        $product = Product::where('id', $productId)
            ->where('business_id', $businessId)
            ->firstOrFail();

        if (!$skipStockCheck && $isValidationDisabled && $product->quantity === 0) {
            throw new \Exception('Insufficient stock');
        }

        // If a specific purchase batch is selected, validate it
        $purchaseItem = null;
        if ($purchaseItemId) {
            $query = PurchaseItem::where('id', $purchaseItemId)
                ->where('product_id', $productId);

            if ($isValidationDisabled && !$skipStockCheck) {
                $query->where('remaining_qty', '>', 0);
            }

            $purchaseItem = $query->firstOrFail();
        }

        // Determine quantity to set (accessories may have a custom override qty)
        $forceQty = $overrideQty; // non-null only for accessories with defined default qty

        // Check if the same product+batch combo is already in cart
        // For accessories, we check within the same parent cart item to avoid collisions
        $cartQuery = PosCart::where('user_id', Auth::id())
            ->where('product_id', $product->id);

        if ($parentCartId) {
            // Accessory: match on parent_cart_id too
            $cartQuery->where('parent_cart_id', $parentCartId);
        } else {
            $cartQuery->whereNull('parent_cart_id');
        }

        $purchaseItemId
        ? $cartQuery->where('purchase_item_id', $purchaseItemId)
        : $cartQuery->whereNull('purchase_item_id');

        $cartItem = $cartQuery->first();

        if ($forceQty !== null) {
            // For accessories, we always set the defined quantity, not increment
            $newQuantity = $forceQty;
        } else {
            $newQuantity = $cartItem ? $cartItem->quantity + 1 : 1;
        }

         // Stock limit check (only when validation is disabled)
        if (!$skipStockCheck && $isValidationDisabled) {
            if ($purchaseItem && $newQuantity > $purchaseItem->remaining_qty) {
                throw new \Exception(
                    'Cannot add more, batch stock limit reached (Available: ' . $purchaseItem->remaining_qty . ')'
                );
            } elseif (!$purchaseItem && $newQuantity > $product->quantity) {
                throw new \Exception('Cannot add more, stock limit reached');
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
                'user_id'               => Auth::id(),
                'product_id'            => $product->id,
                'purchase_item_id'      => $purchaseItemId,
                'quantity'              => $newQuantity,
                'further_tax'           => $furtherTax,
                'parent_cart_id'        => $parentCartId,
                'accessory_default_qty' => $forceQty,
            ]);
        }

        // ── Auto-add accessories (only for TOP-LEVEL products, not accessories of accessories) ──
        if ($parentCartId === null) {
            $this->syncAccessoriesToCart($product, $cartItem, $businessId, $registrationStatus, $isValidationDisabled);
        }

        return $cartItem;
    }

    /**
     * Auto-add or update accessory cart rows for the given parent cart item.
     * Uses FIFO batch selection (oldest available batch).
     * Accessories are ALWAYS added regardless of stock level.
     */
    private function syncAccessoriesToCart(Product $product, PosCart $parentCartItem, $businessId, $registrationStatus, $isValidationDisabled): void
    {
        $accessories = $product->accessories()->get();
 
        foreach ($accessories as $accessory) {
            $defaultQty = (float) $accessory->pivot->quantity;
            if ($defaultQty <= 0) {
                $defaultQty = 1;
            }
 
            // Find best batch (FIFO: oldest batch with stock preferred, but any batch accepted)
            $purchaseItemId = null;
 
            if ($isValidationDisabled) {
                // Prefer a batch that still has stock (FIFO)
                $batch = PurchaseItem::where('product_id', $accessory->id)
                    ->where('remaining_qty', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->first();
 
                // If no batch has stock, fall back to the oldest batch anyway —
                // accessories must always be added with the main product
                if (!$batch) {
                    $batch = PurchaseItem::where('product_id', $accessory->id)
                        ->orderBy('created_at', 'asc')
                        ->first();
                }
            } else {
                // Validation disabled — use any batch
                $batch = PurchaseItem::where('product_id', $accessory->id)
                    ->orderBy('created_at', 'asc')
                    ->first();
            }
 
            $purchaseItemId = $batch ? $batch->id : null;
 
            try {
                // $skipStockCheck = true so accessories bypass all stock limit throws
                $this->addToCart(
                    $accessory->id,
                    $businessId,
                    $registrationStatus,
                    $purchaseItemId,
                    $parentCartItem->id, // parent_cart_id
                    $defaultQty,         // override qty
                    true                 // skipStockCheck — always add accessory
                );
            } catch (\Exception $e) {
                // Log but do not block parent cart add
                Log::warning("Accessory [{$accessory->id}] could not be added to cart: " . $e->getMessage());
            }
        }
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
        $cart = PosCart::with('accessoryCartItems')->findOrFail($id);

        // If this is a parent item, delete all its accessory sub-rows first
        if ($cart->accessoryCartItems->isNotEmpty()) {
            PosCart::where('parent_cart_id', $cart->id)->delete();
        }

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
        $cart = PosCart::with('product')->findOrFail($id);
        // Stock check: batch-aware
         $quantityVal = quantity_validation::where('business_id' , $businessId)->first();
        
        // AFTER — accessories bypass stock check entirely
        $isAccessory = !is_null($cart->parent_cart_id);

        if (!$isAccessory && $quantityVal->product_quantity_validation == "disabled") {
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
