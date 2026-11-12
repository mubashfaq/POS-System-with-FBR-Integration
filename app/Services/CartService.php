<?php

namespace App\Services;

use App\Models\Packing;
use App\Models\PosCart;
use App\Models\Product;
use App\Models\ProductUnitPrice;
use App\Models\ProductVariation;
use App\Models\PurchaseItem;
use App\Models\quantity_validation;
use App\Services\UomConverter;
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
        $variationId = null,
        $qtyUnitId = null
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

        $availableStock = (float) ($variation ? $variation->quantity : $product->quantity);

        if (!$skipStockCheck && $isValidationDisabled && $availableStock <= 0) {
            throw new \Exception('Insufficient stock');
        }

        // If a specific purchase batch is selected, validate it belongs to this product AND variation
        $purchaseItem = null;
        if ($purchaseItemId) {
            $query = PurchaseItem::where('id', $purchaseItemId)
                ->where('product_id', $productId)
                ->where('product_variation_id', $variationId);

            if ($isValidationDisabled && !$skipStockCheck) {
                $query->where('remaining_qty', '>', 0);
            }

            $purchaseItem = $query->firstOrFail();
        }

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

        $packingId = null;
        $resolvedUnitId = null;
        $factor = 1.0;

        if (!$parentCartId && ($product->default_packing_id || $cartItem?->packing_id)) {
            $packingId = (int) ($cartItem?->packing_id ?: $product->default_packing_id);
            if ($qtyUnitId) {
                $resolvedUnitId = (int) $qtyUnitId;
            } elseif ($cartItem && $cartItem->qty_unit_id) {
                $resolvedUnitId = (int) $cartItem->qty_unit_id;
            } elseif ($product->unit_id) {
                $resolvedUnitId = (int) $product->unit_id;
            }

            if ($resolvedUnitId) {
                try {
                    $level = UomConverter::resolve($packingId, $resolvedUnitId);
                    $factor = (float) $level->base_qty;
                } catch (\Throwable $e) {
                    $factor = 1.0;
                }
            }
        } elseif ($cartItem && $cartItem->packing_id && $cartItem->qty_unit_id) {
            $packingId = (int) $cartItem->packing_id;
            $resolvedUnitId = $qtyUnitId ? (int) $qtyUnitId : (int) $cartItem->qty_unit_id;
            try {
                $level = UomConverter::resolve($packingId, $resolvedUnitId);
                $factor = (float) $level->base_qty;
            } catch (\Throwable $e) {
                $factor = 1.0;
            }
        }

        if ($overrideQty !== null) {
            $enteredQty = (float) $overrideQty;
        } elseif ($cartItem) {
            if ($qtyUnitId && $cartItem->qty_unit_id && (int) $cartItem->qty_unit_id !== (int) $qtyUnitId) {
                $enteredQty = 1.0;
            } else {
                $enteredQty = ((float) ($cartItem->entered_qty ?? ($factor > 0 ? $cartItem->quantity / $factor : $cartItem->quantity))) + 1;
            }
        } else {
            $enteredQty = 1.0;
        }

        $newBaseQuantity = round($enteredQty * $factor, 4);

        if (!$skipStockCheck && $isValidationDisabled) {
            if ($purchaseItem && $newBaseQuantity > (float) $purchaseItem->remaining_qty) {
                throw new \Exception('Cannot add more, batch stock limit reached (Available: ' . $purchaseItem->remaining_qty . ')');
            } elseif (!$purchaseItem && $newBaseQuantity > $availableStock) {
                throw new \Exception('Cannot add more, stock limit reached');
            }
        }

        if ($resolvedUnitId) {
            $unitPriceRow = ProductUnitPrice::where('product_id', $product->id)
                ->where('unit_id', $resolvedUnitId)
                ->first();

            if ($unitPriceRow) {
                $enteredUnitPrice = (float) $unitPriceRow->sale_price;
            } else {
                $basePrice = $cartItem?->price_override ?? ($variation ? $variation->price : $product->discounted_price);
                $enteredUnitPrice = round((float) $basePrice * $factor, 4);
            }
        } else {
            $basePrice = $cartItem?->price_override ?? ($variation ? $variation->price : $product->discounted_price);
            $enteredUnitPrice = (float) $basePrice;
        }

        $baseTotal = round($enteredQty * $enteredUnitPrice, 2);
        $furtherTax = $this->calculateFurtherTax($product, $baseTotal, $registrationStatus);

        if ($cartItem) {
            $cartItem->packing_id = $packingId;
            $cartItem->qty_unit_id = $resolvedUnitId;
            $cartItem->quantity = $newBaseQuantity;
            $cartItem->entered_qty = $packingId ? $enteredQty : null;
            $cartItem->entered_unit_price = $packingId ? $enteredUnitPrice : null;
            $cartItem->further_tax = $furtherTax;
            $cartItem->row_total = round($baseTotal + $furtherTax, 2);
            $cartItem->save();
        } else {
            $cartItem = PosCart::create([
                'user_id' => Auth::id(),
                'product_id' => $product->id,
                'product_variation_id' => $variationId,
                'purchase_item_id' => $purchaseItemId,
                'packing_id' => $packingId,
                'qty_unit_id' => $resolvedUnitId,
                'entered_qty' => $packingId ? $enteredQty : null,
                'entered_unit_price' => $packingId ? $enteredUnitPrice : null,
                'quantity' => $newBaseQuantity,
                'further_tax' => $furtherTax,
                'row_total' => round($baseTotal + $furtherTax, 2),
                'parent_cart_id' => $parentCartId,
                'accessory_default_qty' => $overrideQty,
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
        $variationId = null,
        $qtyUnitId = null
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
        }

        $availableStock = (float) ($variation ? $variation->quantity : ($product->quantity ?? 0));

        if (!$skipStockCheck && $isValidationDisabled && $availableStock <= 0) {
            throw new \Exception('Insufficient stock');
        }

        $purchaseItem = null;
        if ($purchaseItemId) {
            $query = PurchaseItem::where('id', $purchaseItemId)
                ->where('product_id', $productId)
                ->where('product_variation_id', $variationId);

            if ($isValidationDisabled && !$skipStockCheck) {
                $query->where('remaining_qty', '>', 0);
            }

            $purchaseItem = $query->firstOrFail();
        }

        $packingId = null;
        $resolvedUnitId = null;
        $factor = 1.0;

        if (!$parentCartId && $product->default_packing_id) {
            $packingId = (int) $product->default_packing_id;
            if ($qtyUnitId) {
                $resolvedUnitId = (int) $qtyUnitId;
            } elseif ($product->unit_id) {
                $resolvedUnitId = (int) $product->unit_id;
            }

            if ($resolvedUnitId) {
                try {
                    $level = UomConverter::resolve($packingId, $resolvedUnitId);
                    $factor = (float) $level->base_qty;
                } catch (\Throwable $e) {
                    $factor = 1.0;
                }
            }
        }

        $enteredQty = $overrideQty !== null ? (float) $overrideQty : 1.0;
        $newBaseQuantity = round($enteredQty * $factor, 4);

        if (!$skipStockCheck && $isValidationDisabled) {
            if ($purchaseItem && $newBaseQuantity > (float) $purchaseItem->remaining_qty) {
                throw new \Exception('Cannot add more, batch stock limit reached (Available: ' . $purchaseItem->remaining_qty . ')');
            } elseif (!$purchaseItem && $newBaseQuantity > $availableStock) {
                throw new \Exception('Cannot add more, stock limit reached');
            }
        }

        if ($resolvedUnitId) {
            $unitPriceRow = ProductUnitPrice::where('product_id', $product->id)
                ->where('unit_id', $resolvedUnitId)
                ->first();

            if ($unitPriceRow) {
                $enteredUnitPrice = (float) $unitPriceRow->sale_price;
            } else {
                $basePrice = $variation ? $variation->price : $product->discounted_price;
                $enteredUnitPrice = round((float) $basePrice * $factor, 4);
            }
        } else {
            $basePrice = $variation ? $variation->price : $product->discounted_price;
            $enteredUnitPrice = (float) $basePrice;
        }

        $baseTotal = round($enteredQty * $enteredUnitPrice, 2);
        $furtherTax = $this->calculateFurtherTax($product, $baseTotal, $registrationStatus);

        $cartItem = PosCart::create([
            'user_id' => Auth::id(),
            'product_id' => $product->id,
            'product_variation_id' => $variationId,
            'purchase_item_id' => $purchaseItemId,
            'packing_id' => $packingId,
            'qty_unit_id' => $resolvedUnitId,
            'entered_qty' => $packingId ? $enteredQty : null,
            'entered_unit_price' => $packingId ? $enteredUnitPrice : null,
            'quantity' => $newBaseQuantity,
            'further_tax' => $furtherTax,
            'row_total' => round($baseTotal + $furtherTax, 2),
            'parent_cart_id' => $parentCartId,
            'accessory_default_qty' => $overrideQty,
        ]);

        if ($parentCartId === null) {
            $this->syncAccessoriesToCart($product, $cartItem, $businessId, $registrationStatus, $isValidationDisabled);
        }

        return $cartItem;
    }

    private function syncAccessoriesToCart(Product $product, PosCart $parentCartItem, $businessId, $registrationStatus, $isValidationDisabled): void
    {
        $accessories = $product->accessories()->get();

        foreach ($accessories as $accessory) {
            $defaultQty = (float) $accessory->pivot->quantity;
            if ($defaultQty <= 0) {
                $defaultQty = 1;
            }

            $batch = PurchaseItem::where('product_id', $accessory->id)
                ->where('remaining_qty', '>', 0)
                ->orderBy('created_at', 'asc')
                ->first()
                ?? PurchaseItem::where('product_id', $accessory->id)
                    ->orderBy('created_at', 'asc')
                    ->first();

            $purchaseItemId = $batch ? $batch->id : null;

            try {
                $this->addToCart(
                    $accessory->id,
                    $businessId,
                    $registrationStatus,
                    $purchaseItemId,
                    $parentCartItem->id,
                    $defaultQty,
                    true
                );
            } catch (\Exception $e) {
                Log::warning("Accessory [{$accessory->id}] could not be added to cart: " . $e->getMessage());
            }
        }
    }

    private function calculateFurtherTax($product, $baseTotal, $registrationStatus)
    {
        $registrationStatus = strtolower((string) $registrationStatus);
        $furtherTax = 0;

        if (strtolower(trim((string) $product->sale_type)) != '3rd schedule goods' && $registrationStatus === 'unregistered') {
            $rateText = trim((string) $product->rate_of_tax);

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
        $cart = PosCart::with(['product', 'variation', 'purchaseItem'])->findOrFail($id);

        $factor = 1.0;
        if ($cart->packing_id && $cart->qty_unit_id) {
            $level = UomConverter::resolve($cart->packing_id, $cart->qty_unit_id);
            $factor = (float) $level->base_qty;
        }

        $enteredQty = ($cart->packing_id ? (float) ($cart->entered_qty ?? 1) : (float) $cart->quantity) + 1;
        $newBaseQty = round($enteredQty * $factor, 4);

        if ($quantityVal && $quantityVal->product_quantity_validation == "disabled") {
            $availableStock = (float) ($cart->variation ? $cart->variation->quantity : ($cart->product->quantity ?? 0));

            if ($availableStock <= 0) {
                throw new \Exception('Insufficient stock available');
            }

            if ($cart->purchase_item_id) {
                $purchaseItem = $cart->purchaseItem ?? PurchaseItem::findOrFail($cart->purchase_item_id);
                if ($newBaseQty > (float) $purchaseItem->remaining_qty) {
                    throw new \Exception('Cannot add more, batch stock limit reached (Available: ' . $purchaseItem->remaining_qty . ')');
                }
            } else {
                if ($newBaseQty > $availableStock) {
                    throw new \Exception('Cannot add more, stock limit reached');
                }
            }
        }

        $cart->quantity = $newBaseQty;
        if ($cart->packing_id) {
            $cart->entered_qty = $enteredQty;
        }

        $linePrice = $cart->entered_unit_price
            ?? (($cart->price_override ?? $cart->variation?->price ?? $cart->product->discounted_price) * $factor);
        $baseTotal = round($enteredQty * $linePrice, 2);
        $registrationStatus = session('customer_is_registered') ?? 'registered';
        $furtherTax = $this->calculateFurtherTax($cart->product, $baseTotal, $registrationStatus);

        $cart->further_tax = $furtherTax;
        $cart->row_total = round($baseTotal + $furtherTax, 2);
        $cart->save();

        return $cart;
    }

    public function decrement($id)
    {
        $cart = PosCart::with(['product', 'variation'])->findOrFail($id);

        $factor = 1.0;
        if ($cart->packing_id && $cart->qty_unit_id) {
            $level = UomConverter::resolve($cart->packing_id, $cart->qty_unit_id);
            $factor = (float) $level->base_qty;
        }

        $enteredQty = ($cart->packing_id ? (float) ($cart->entered_qty ?? 1) : (float) $cart->quantity) - 1;
        if ($enteredQty < 1) {
            throw new \Exception('Quantity cannot be less than 1.');
        }

        $newBaseQty = round($enteredQty * $factor, 4);
        $cart->quantity = $newBaseQty;
        if ($cart->packing_id) {
            $cart->entered_qty = $enteredQty;
        }

        $linePrice = $cart->entered_unit_price
            ?? (($cart->price_override ?? $cart->variation?->price ?? $cart->product->discounted_price) * $factor);
        $baseTotal = round($enteredQty * $linePrice, 2);
        $registrationStatus = session('customer_is_registered') ?? 'registered';
        $furtherTax = $this->calculateFurtherTax($cart->product, $baseTotal, $registrationStatus);

        $cart->further_tax = $furtherTax;
        $cart->row_total = round($baseTotal + $furtherTax, 2);
        $cart->save();

        return $cart;
    }

    public function delete($id)
    {
        $cart = PosCart::with('accessoryCartItems')->findOrFail($id);

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
        $cart = PosCart::with(['product', 'variation', 'purchaseItem'])->findOrFail($id);
        $quantityVal = quantity_validation::where('business_id', $businessId)->first();
        $isAccessory = !is_null($cart->parent_cart_id);

        $factor = 1.0;
        if ($cart->packing_id && $cart->qty_unit_id) {
            $level = UomConverter::resolve($cart->packing_id, $cart->qty_unit_id);
            $factor = (float) $level->base_qty;
        }

        $enteredQty = (float) $quantity;
        if ($enteredQty < 0.0001) {
            throw new \Exception('Quantity cannot be less than 0.0001.');
        }

        $newBaseQty = round($enteredQty * $factor, 4);

        if (!$isAccessory && $quantityVal && $quantityVal->product_quantity_validation == "disabled") {
            if ($cart->purchase_item_id) {
                $purchaseItem = $cart->purchaseItem ?? PurchaseItem::findOrFail($cart->purchase_item_id);
                if ($newBaseQty > (float) $purchaseItem->remaining_qty) {
                    throw new \Exception('Cannot add more, batch stock limit reached (Available: ' . $purchaseItem->remaining_qty . ')');
                }
            } else {
                $availableStock = (float) ($cart->variation ? $cart->variation->quantity : ($cart->product->quantity ?? 0));
                if ($newBaseQty > $availableStock) {
                    throw new \Exception('Cannot add more, stock limit reached');
                }
            }
        }

        $cart->quantity = $newBaseQty;
        if ($cart->packing_id) {
            $cart->entered_qty = $enteredQty;
        }

        $linePrice = $cart->entered_unit_price
            ?? (($cart->price_override ?? $cart->variation?->price ?? $cart->product->discounted_price) * $factor);
        $baseTotal = round($enteredQty * $linePrice, 2);

        $furtherTax = $this->calculateFurtherTax(
            $cart->product,
            $baseTotal,
            $registrationStatus
        );

        $cart->further_tax = $furtherTax;
        $cart->row_total = round($baseTotal + $furtherTax, 2);
        $cart->save();

        return $cart;
    }

    public function updatePrice($id, $price)
    {
        $cart = PosCart::with(['product', 'variation'])->findOrFail($id);

        if ($cart->packing_id) {
            $cart->entered_unit_price = $price !== null ? (float) $price : null;
            $linePrice = (float) ($cart->entered_unit_price ?? $cart->product->discounted_price);
            $enteredQty = (float) ($cart->entered_qty ?? $cart->quantity);
            $cart->row_total = round($linePrice * $enteredQty, 2);
        } else {
            $cart->price_override = $price !== null ? (float) $price : null;
            $finalPrice = (float) ($cart->price_override ?? $cart->variation?->price ?? $cart->product->discounted_price);
            $cart->row_total = round($finalPrice * (float) $cart->quantity, 2);
        }

        $cart->save();

        return $cart;
    }

    /**
     * Change packaging unit on a cart row.
     */
    public function setUnit($id, $qtyUnitId, $registrationStatus, $businessId)
    {
        $cart = PosCart::with(['product', 'variation', 'purchaseItem'])->findOrFail($id);
        if (!$cart->packing_id) {
            if ($cart->product && $cart->product->default_packing_id) {
                $cart->packing_id = $cart->product->default_packing_id;
            } else {
                throw new \Exception('Cart item does not have a packing assigned.');
            }
        }

        $level = UomConverter::resolve($cart->packing_id, $qtyUnitId);
        $factor = (float) $level->base_qty;

        $cart->qty_unit_id = $qtyUnitId;
        $enteredQty = (float) ($cart->entered_qty ?? 1);
        $newBaseQty = round($enteredQty * $factor, 4);

        // Suggested price for the new unit
        $unitPriceRow = ProductUnitPrice::where('product_id', $cart->product_id)
            ->where('unit_id', $qtyUnitId)
            ->first();

        if ($unitPriceRow) {
            $cart->entered_unit_price = (float) $unitPriceRow->sale_price;
        } else {
            $basePrice = $cart->price_override ?? $cart->variation?->price ?? $cart->product->discounted_price;
            $cart->entered_unit_price = round((float) $basePrice * $factor, 4);
        }

        // Check stock with base quantity
        $quantityVal = quantity_validation::where('business_id', $businessId)->first();
        $isValidationDisabled = $quantityVal && $quantityVal->product_quantity_validation === 'disabled';

        if ($isValidationDisabled) {
            if ($cart->purchase_item_id) {
                $purchaseItem = $cart->purchaseItem ?? PurchaseItem::findOrFail($cart->purchase_item_id);
                if ($newBaseQty > (float) $purchaseItem->remaining_qty) {
                    throw new \Exception('Cannot change unit, batch stock limit reached (Available: ' . $purchaseItem->remaining_qty . ')');
                }
            } else {
                $availableStock = (float) ($cart->variation ? $cart->variation->quantity : ($cart->product->quantity ?? 0));
                if ($newBaseQty > $availableStock) {
                    throw new \Exception('Cannot change unit, stock limit reached (Available: ' . $availableStock . ')');
                }
            }
        }

        $cart->quantity = $newBaseQty;
        $linePrice = (float) $cart->entered_unit_price;
        $lineTotal = round($enteredQty * $linePrice, 2);
        $furtherTax = $this->calculateFurtherTax($cart->product, $lineTotal, $registrationStatus);
        $cart->further_tax = $furtherTax;
        $cart->row_total = round($lineTotal + $furtherTax, 2);
        $cart->save();

        return $cart;
    }
}
