<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosCart extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'custom_fields' => 'array',
        'quantity' => 'decimal:4',
        'entered_qty' => 'decimal:4',
        'entered_unit_price' => 'decimal:4',
        'price_override' => 'decimal:4',
    ];

    public function packing()
    {
        return $this->belongsTo(Packing::class, 'packing_id');
    }

    public function qtyUnit()
    {
        return $this->belongsTo(Unit::class, 'qty_unit_id');
    }

    protected $appends = [
        'variation_label',
        'available_stock',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseItem()
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    /**
     * The parent cart item (set when this row is an accessory of another item).
     */
    public function parentCart()
    {
        return $this->belongsTo(PosCart::class, 'parent_cart_id');
    }

    /**
     * Accessory cart rows belonging to this parent cart item.
     */
    public function accessoryCartItems()
    {
        return $this->hasMany(PosCart::class, 'parent_cart_id');
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id')->withTrashed();
    }

    public function getVariationLabelAttribute()
    {
        return $this->variation?->label;
    }

    /**
     * Available stock for this cart row.
     */
    public function getAvailableStockAttribute(): float
    {
        if ($this->purchase_item_id) {
            return round((float) optional($this->purchaseItem)->remaining_qty, 4);
        }

        return round((float) ($this->variation ? $this->variation->quantity : ($this->product->quantity ?? 0)), 4);
    }

    /**
     * Convenience: is this row an accessory sub-item?
     */
    public function isAccessory(): bool
    {
        return !is_null($this->parent_cart_id);
    }
}
