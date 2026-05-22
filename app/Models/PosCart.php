<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosCart extends Model
{
    use HasFactory;

    protected $guarded = [];

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

    /**
     * Convenience: is this row an accessory sub-item?
     */
    public function isAccessory(): bool
    {
        return !is_null($this->parent_cart_id);
    }
}
