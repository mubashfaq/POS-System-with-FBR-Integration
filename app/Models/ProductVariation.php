<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariation extends Model
{
    protected $fillable = [
        'product_id',
        'business_id',
        'sku',
        'purchase_price',
        'price',
        'status',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'price' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function values()
    {
        return $this->belongsToMany(AttributeValue::class, 'product_variation_values');
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class, 'product_variation_id');
    }

    // Overrides the old column. Uses the eager-loaded sum when present (no N+1).
    public function getQuantityAttribute($value): float
    {
        if (array_key_exists('stock_sum', $this->attributes)) {
            return (float) $this->attributes['stock_sum'];
        }

        return (float) $this->purchaseItems()->sum('remaining_qty');
    }

    public function getLabelAttribute(): string
    {
        return $this->values->pluck('value')->implode(' / ');
    }
}
