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
        'purchase_price',
        'price',
        'discount_value',
        'discount_type',
        'quantity',
        'remaining_qty',
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
    // Accessor for Product Name
    public function getNameAttribute()
    {
        return $this->product ? $this->product->name : null;
    }

    // Accessor for Current Stock Quantity
    public function getStockAttribute()
    {
        return $this->product ? $this->product->quantity : null;
    }
}
