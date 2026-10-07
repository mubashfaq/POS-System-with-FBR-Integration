<?php

namespace App\Services;

use App\Models\PosCart;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\PurchaseItem;
use App\Models\quantity_validation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CartService
{

    public function addToCart(
        $productId,
        $businessId,
        $registrationStatus,
        $purchaseItemId = null,
        $parentCartId = null,
        $overrideQty = null,
        $skipStockCheck = false,
        $variationId = null
    ) {
        $businessId = session('business_id') ?: $businessId;

        $quantityVal = quantity_validation::where('business_id', $businessId)->firstOrFail();
        $isValidationDisabled = $quantityVal->product_quantity_validation === 'disabled';

        $product = Product::where('id', $productId)
            ->where('business_id', $businessId)
            ->firstOrFail();

        $variation = null;

        if ($product->has_variations) {
            abort_if(!$variationId, 422, "Select a variation for {$product->name}.");

            $variation = ProductVariation::where('id', $variationId)
                ->where('product_id', $product->id)
                ->where('status', 1)
                ->firstOrFail();
        } else {
            abort_if($variationId, 422, "{$product->name} has no variations.");
        }

        $availableStock = $variation ? $variation->quantity : $product->quantity;

        if (!$skipStockCheck && $isValidationDisabled && $availableStock == 0) {
            throw new \Exception('Insufficient stock');
        }

        // If a specific purchase batch is selected, validate it belongs to this product AND variation
        $purchaseItem = null;
        if ($purchaseItemId) {
            $query = PurchaseItem::where('id', $purchaseItemId)
                ->where('product_id', $productId)
                ->where('product_variation_id', $variationId); // null === null for simple products

            if ($isValidationDisabled && !$skipStockCheck) {
                $query->where('remaining_qty', '>', 0);
            }

            $purchaseItem = $query->firstOrFail();
        }

        $forceQty = $overrideQty;

        $cartQuery = PosCart::where('user_id', Auth::id())
            ->where('product_id', $product->id)
            ->where('product_variation_id', $variationId);

        if ($parentCartId) {
            $cartQuery->where('parent_cart_id', $parentCartId);
        } else {
            $cartQuery->whereNull('parent_cart_id');
        }

        $purchaseItemId
            ? $cartQuery->where('purchase_item_id', $purchaseItemId)
            : $cartQuery->whereNull('purchase_item_id');

        $cartItem = $cartQuery->first();

        $newQuantity = $forceQty !== null
            ? $forceQty
            : ($cartItem ? $cartItem->quantity + 1 : 1);

        if (!$skipStockCheck && $isValidationDisabled) {
            if ($purchaseItem && $newQuantity > $purchaseItem->remaining_qty) {
                throw new \Exception('Cannot add more, batch stock limit reached (Available: ' . $purchaseItem->remaining_qty . ')');
            } elseif (!$purchaseItem && $newQuantity > $availableStock) {
                throw new \Exception('Cannot add more, stock limit reached');
            }
        }

        $unitPrice = $variation ? $variation->price : $product->discounted_price;
        $baseTotal = $unitPrice * $newQuantity;
        $furtherTax = $this->calculateFurtherTax($product, $baseTotal, $registrationStatus);

        if ($cartItem) {
            $cartItem->quantity = $newQuantity;
            $cartItem->further_tax = $furtherTax;
            $cartItem->save();
        } else {
            $cartItem = PosCart::create([
                'user_id' => Auth::id(),
                'product_id' => $product->id,
                'product_variation_id' => $variationId,
                'purchase_item_id' => $purchaseItemId,
                'quantity' => $newQuantity,
                'further_tax' => $furtherTax,
                'parent_cart_id' => $parentCartId,
                'accessory_default_qty' => $forceQty,
            ]);
        }

        if ($parentCartId === null) {
            $this->syncAccessoriesToCart($product, $cartItem, $businessId, $registrationStatus, $isValidationDisabled);
        }

        return $cartItem;
    }




    public function addToCartNew(
        $productId,
        $businessId,
        $registrationStatus,
        $purchaseItemId = null,
        $parentCartId = null,
        $overrideQty = null,
        $skipStockCheck = false,
        $variationId = null
    ) {
        $businessId = session('business_id') ?: $businessId;

        $quantityVal = quantity_validation::where('business_id', $businessId)->firstOrFail();
        $isValidationDisabled = $quantityVal->product_quantity_validation === 'disabled';

        $product = Product::where('id', $productId)
            ->where('business_id', $businessId)
            ->firstOrFail();

        if (!$skipStockCheck && $isValidationDisabled && $product->quantity === 0) {
            throw new \Exception('Insufficient stock');
        }

        $purchaseItem = null;
        if ($purchaseItemId) {
            $query = PurchaseItem::where('id', $purchaseItemId)
                ->where('product_id', $productId);

            if ($isValidationDisabled && !$skipStockCheck) {
                $query->where('remaining_qty', '>', 0);
            }

            $purchaseItem = $query->firstOrFail();
        }

        $forceQty = $overrideQty;

        // ✅ Hamesha quantity 1 (ya forceQty) — koi existing row check nahi
        $newQuantity = $forceQty !== null ? $forceQty : 1;

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

        $unitPrice = $product->discounted_price;
        $baseTotal = $unitPrice * $newQuantity;

        $furtherTax = $this->calculateFurtherTax(
            $product,
            $baseTotal,
            $registrationStatus
        );

        // ✅ Hamesha naya row banao
        $cartItem = PosCart::create([
            'user_id' => Auth::id(),
            'product_id' => $product->id,
            'purchase_item_id' => $purchaseItemId,
            'quantity' => $newQuantity,
            'further_tax' => $furtherTax,
            'parent_cart_id' => $parentCartId,
            'accessory_default_qty' => $forceQty,
        ]);

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

        if (strtolower(trim($product->sale_type)) != '3rd schedule goods' && $registrationStatus === 'unregistered') {
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
        $quantityVal = quantity_validation::where('business_id', $businessId)->first();
        $cart = PosCart::with(['product', 'variation'])->findOrFail($id);

        if ($quantityVal->product_quantity_validation == "disabled") {
            $availableStock = $cart->variation ? $cart->variation->quantity : $cart->product->quantity;

            if ($availableStock == 0) {
                throw new \Exception('Insufficient stock available');
            }

            if ($cart->purchase_item_id) {
                $purchaseItem = PurchaseItem::findOrFail($cart->purchase_item_id);
                if ($cart->quantity >= $purchaseItem->remaining_qty) {
                    throw new \Exception('Cannot add more, batch stock limit reached (Available: ' . $purchaseItem->remaining_qty . ')');
                }
            } else {
                if ($cart->quantity >= $availableStock) {
                    throw new \Exception('Cannot add more, stock limit reached');
                }
            }
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
        $cart = PosCart::with(['product', 'variation'])->findOrFail($id);
        $quantityVal = quantity_validation::where('business_id', $businessId)->first();
        $isAccessory = !is_null($cart->parent_cart_id);

        if (!$isAccessory && $quantityVal->product_quantity_validation == "disabled") {
            if ($cart->purchase_item_id) {
                $purchaseItem = PurchaseItem::findOrFail($cart->purchase_item_id);
                if ($quantity > $purchaseItem->remaining_qty) {
                    throw new \Exception('Cannot add more, batch stock limit reached (Available: ' . $purchaseItem->remaining_qty . ')');
                }
            } else {
                $availableStock = $cart->variation ? $cart->variation->quantity : ($cart->product->quantity ?? 0);
                if ($quantity > $availableStock) {
                    throw new \Exception('Cannot add more, stock limit reached');
                }
            }
        }

        $cart->quantity = $quantity;

        $unitPrice = $cart->variation?->price ?? $cart->product->discounted_price;
        $baseTotal = $quantity * $unitPrice;

        $product = $cart->product;

        $furtherTax = $this->calculateFurtherTax(
            $product,
            $baseTotal,
            $registrationStatus
        );

        $cart->row_total = round($baseTotal + $furtherTax, 2);
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
