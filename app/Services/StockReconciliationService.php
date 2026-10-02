<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\PurchaseItem;

class StockReconciliationService
{
    /**
     * Reconcile negative stock and negative batches when a new purchase item is created.
     * Must be called within a DB transaction.
     *
     * @param PurchaseItem $purchaseItem
     * @return void
     */
    public static function reconcileNewBatch(PurchaseItem $purchaseItem)
    {
        $product = Product::lockForUpdate()->find($purchaseItem->product_id);
        if (!$product) {
            return;
        }

        // Only require a real variation row when this batch actually has one.
        // product_variation_id is legitimately null for simple products.
        if ($purchaseItem->product_variation_id) {
            $variation = ProductVariation::lockForUpdate()->find($purchaseItem->product_variation_id);
            if (!$variation) {
                return;
            }
        }

        $purchaseQty = $purchaseItem->quantity;

        // Oversold amount for this exact unit (product + variation), from the
        // batches, not from a product-level column.
        $unitStock = PurchaseItem::where('product_id', $purchaseItem->product_id)
            ->where('product_variation_id', $purchaseItem->product_variation_id) // null === null
            ->where('id', '!=', $purchaseItem->id)
            ->sum('remaining_qty');

        if ($unitStock < 0) {
            $remaining = $purchaseQty;

            $negativeBatches = PurchaseItem::where('product_id', $purchaseItem->product_id)
                ->where('product_variation_id', $purchaseItem->product_variation_id)
                ->where('remaining_qty', '<', 0)
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($negativeBatches as $batch) {
                if ($remaining <= 0)
                    break;

                $offset = min($remaining, abs($batch->remaining_qty));
                $batch->remaining_qty += $offset;
                $batch->save();
                $remaining -= $offset;
            }

            $purchaseItem->remaining_qty = $remaining;
            $purchaseItem->save();
        } else {
            if (is_null($purchaseItem->remaining_qty)) {
                $purchaseItem->remaining_qty = $purchaseQty;
                $purchaseItem->save();
            }
        }
    }
}
