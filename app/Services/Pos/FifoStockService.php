<?php
// app/Services/Pos/FifoStockService.php
namespace App\Services\Pos;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\PurchaseItem;

class FifoStockService
{
    /**
     * Consume qty from the oldest batches. Quantity is always BASE qty.
     * Must run inside a DB transaction.
     * Returns the allocations:
     * [['purchase_item_id' => id|null, 'qty' => x, 'cost' => y], ...]
     */
    public function consume(Product $product, float $qty, bool $enforce, ?int $purchaseItemId = null, ?int $variationId = null): array
    {
        $left = round($qty, 4);
        $allocations = [];

        if ($purchaseItemId && ($batch = PurchaseItem::lockForUpdate()->find($purchaseItemId))) {
            // enforcement on: take only what the batch has, the rest goes FIFO. Off: old behaviour.
            $take = round(max($enforce ? min((float) $batch->remaining_qty, $left) : $left, 0), 4);
            if ($take > 0) {
                $batch->decrement('remaining_qty', $take);
                $allocations[] = [
                    'purchase_item_id' => $batch->id,
                    'qty' => $take,
                    'cost' => (float) $batch->purchase_price,
                ];
                $left = round($left - $take, 4);
            }
            if ($left <= 0) {
                return $allocations;
            }
        }

        $batches = PurchaseItem::where('product_id', $product->id)
            ->where('product_variation_id', $variationId)
            ->where('remaining_qty', '>', 0)
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $b) {
            if ($left <= 0) {
                break;
            }
            $take = round(min((float) $b->remaining_qty, $left), 4);
            if ($take <= 0) {
                continue;
            }
            $b->decrement('remaining_qty', $take);
            $allocations[] = [
                'purchase_item_id' => $b->id,
                'qty' => $take,
                'cost' => (float) $b->purchase_price,
            ];
            $left = round($left - $take, 4);
        }

        if ($left > 0) {
            if ($enforce) {
                throw new InsufficientStockException($product->name);
            }
            $last = $batches->last() ?? PurchaseItem::where('product_id', $product->id)
                ->where('product_variation_id', $variationId)
                ->latest('created_at')
                ->first();
            if ($last) {
                $last->decrement('remaining_qty', $left);
            }
            $allocations[] = [
                'purchase_item_id' => $last?->id,
                'qty' => $left,
                'cost' => (float) ($last?->purchase_price ?? $product->purchase_price),
            ];
        }

        return $allocations;
    }
}
