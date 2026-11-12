<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $baseUnitTitle = $this->unit?->title ?? 'Unit';
        $totalStock = (float) $this->quantity;
        $formattedTotal = (float) $totalStock == (int) $totalStock ? (int) $totalStock : round($totalStock, 4);
        $baseDisplay = "{$formattedTotal} {$baseUnitTitle}";

        $stockDisplay = $baseDisplay;
        $packingLevels = [];

        if ($this->defaultPacking) {
            $levels = $this->defaultPacking->levels->sortBy('level');
            $remaining = $totalStock;
            $parts = [];

            foreach ($levels as $level) {
                $bQty = (float) $level->base_qty;
                $uTitle = $level->unit?->title ?? 'Unit';

                $unitPrice = $this->unitPrices?->firstWhere('unit_id', $level->unit_id)?->sale_price;
                $baseProductPrice = (float) ($this->discounted_price ?: $this->price);
                $suggestedPrice = $unitPrice !== null ? (float) $unitPrice : round($baseProductPrice * $bQty, 2);

                $packingLevels[] = [
                    'unit_id' => $level->unit_id,
                    'title' => $uTitle,
                    'base_qty' => $bQty,
                    'price' => (float) $suggestedPrice,
                    'suggested_price' => (float) $suggestedPrice,
                ];

                if ($bQty > 1) {
                    $count = (int) floor(round($remaining, 4) / $bQty);
                    if ($count > 0) {
                        $parts[] = "{$count} {$uTitle}";
                        $remaining = round($remaining - ($count * $bQty), 4);
                    }
                } else {
                    if ($remaining > 0) {
                        $formattedRem = (float) $remaining == (int) $remaining ? (int) $remaining : $remaining;
                        $parts[] = "{$formattedRem} {$uTitle}";
                        $remaining = 0;
                    }
                }
            }

            if (!empty($parts)) {
                $stockDisplay = "{$baseDisplay} (" . implode(' ', $parts) . ")";
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'image' => $this->image,
            'price' => (float) $this->price,
            'purchase_price' => (float) $this->purchase_price,
            'discounted_price' => (float) $this->discounted_price,
            'quantity' => $totalStock,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'unit_id' => $this->unit_id,
            'base_unit_id' => $this->unit_id,
            'base_unit_name' => $baseUnitTitle,
            'default_packing_id' => $this->default_packing_id,
            'packing_levels' => $packingLevels,
            'stock_display' => $stockDisplay,
            'has_variations' => (bool) $this->has_variations,
            'variations' => $this->when($this->has_variations, fn() =>
                $this->variations->values()->map(fn($v) => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'label' => $v->label,
                    'price' => (float) $v->price,
                    'purchase_price' => (float) $v->purchase_price,
                    'stock' => (float) ($v->quantity ?? 0),
                ])),
        ];
    }
}
