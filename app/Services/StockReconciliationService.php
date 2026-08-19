<?php

namespace App\Services;

use App\Models\Product;
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
        // 1. Lock product row
        $product = Product::lockForUpdate()->find($purchaseItem->product_id);
        if (!$product) {
            return;
        }

        $purchaseQty = $purchaseItem->quantity;

        // If the product's quantity before this purchase was negative
        if ($product->quantity < 0) {
            $oversoldQty = abs($product->quantity);
            $remaining = $purchaseQty;

            // 2. Lock and fetch negative batches (oldest first)
            $negativeBatches = PurchaseItem::where('product_id', $product->id)
                ->where('remaining_qty', '<', 0)
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($negativeBatches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $batchNegQty = abs($batch->remaining_qty);
                $offset = min($remaining, $batchNegQty);

                $batch->remaining_qty += $offset;
                $batch->save();

                $remaining -= $offset;
                $oversoldQty -= $offset;
            }

            // 3. Deduct any remaining oversold quantity (e.g. from FIFO/new products)
            if ($oversoldQty > 0) {
                $remaining = max(0, $remaining - $oversoldQty);
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
