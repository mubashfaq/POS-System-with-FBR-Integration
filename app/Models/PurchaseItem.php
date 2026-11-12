<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'product_variation_id',
        'purchase_price',
        'price',
        'discount_value',
        'discount_type',
        'quantity',
        'remaining_qty',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'remaining_qty' => 'decimal:4',
        'purchase_price' => 'decimal:4',
        'price' => 'decimal:4',
    ];

    /**
     * Scope: only batches that still have unsold stock
     */
    public function scopeAvailable($query)
    {
        return $query->where('remaining_qty', '>', 0);
    }
    protected $appends = ['name', 'stock'];
    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id')->withTrashed();
    }
    // Accessor for Product Name
    public function getNameAttribute()
    {
        return $this->product ? $this->product->name : null;
    }

    public function getVariationLabelAttribute()
    {
        return $this->variation?->label;
    }

    // Make your existing stock accessor variation-aware
    public function getStockAttribute()
    {
        return $this->variation ? $this->variation->quantity : $this->product?->quantity;
    }
}
