<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'image' => $this->image,
            // 'barcode' => $this->barcode,
            'price' => $this->price,
            'purchase_price' => $this->purchase_price,
            'discounted_price' => $this->discounted_price,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'created_at' => $this->created_at,
            // 'image_url' => $this->getImageUrl(),
            'has_variations' => (bool) $this->has_variations,
            'variations' => $this->when($this->has_variations, fn() =>
                $this->variations->where('status', 1)->values()->map(fn($v) => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'label' => $v->label,
                    'price' => $v->price,
                    'purchase_price' => $v->purchase_price,
                    'stock' => (float) ($v->quantity ?? 0),
                ])),
        ];
    }
}
