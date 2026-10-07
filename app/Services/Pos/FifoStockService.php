<?php
// app/Services/Pos/FifoStockService.php
namespace App\Services\Pos;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\PurchaseItem;

class FifoStockService
{
    /**
     * Consume qty from the oldest batches. Returns the allocations:
     * [['purchase_item_id' => id|null, 'qty' => x, 'cost' => y], ...]
     */
    public function consume(Product $product, float $qty, bool $enforce, ?int $purchaseItemId = null, ?int $variationId = null): array
    {
        if ($purchaseItemId) {
            $batch = PurchaseItem::lockForUpdate()->find($purchaseItemId);
            if ($batch) {
                $batch->decrement('remaining_qty', $qty);

                return [
                    [
                        'purchase_item_id' => $batch->id,
                        'qty' => $qty,
                        'cost' => $batch->purchase_price,
                    ]
                ];
            }
        }

        $batches = PurchaseItem::where('product_id', $product->id)
            ->where('product_variation_id', $variationId)
            ->where('remaining_qty', '>', 0)
            ->orderBy('created_at')->orderBy('id')
            ->lockForUpdate()
            ->get();

        $left = $qty;
        $allocations = [];

        foreach ($batches as $batch) {
            if ($left <= 0)
                break;
            $take = min($batch->remaining_qty, $left);
            $batch->decrement('remaining_qty', $take);
            $allocations[] = [
                'purchase_item_id' => $batch->id,
                'qty' => $take,
                'cost' => $batch->purchase_price,
            ];
            $left -= $take;
        }

        if ($left > 0) {
            if ($enforce) {
                throw new InsufficientStockException($product->name);
            }

            $last = $batches->last()
                ?? PurchaseItem::where('product_id', $product->id)
                    ->where('product_variation_id', $variationId)
                    ->latest('created_at')->first();

            if ($last) {
                $last->decrement('remaining_qty', $left);
            }
            $allocations[] = [
                'purchase_item_id' => $last?->id,
                'qty' => $left,
                'cost' => $last?->purchase_price ?? $product->purchase_price,
            ];
        }

        return $allocations;
    }
}