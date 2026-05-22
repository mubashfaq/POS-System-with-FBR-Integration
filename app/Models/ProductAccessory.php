<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAccessory extends Model
{
    use HasFactory;

    protected $table = 'product_accessories';

    protected $fillable = [
        'product_id',
        'accessory_product_id',
        'quantity',
    ];

    /**
     * The main (parent) product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * The accessory (sub) product.
     */
    public function accessoryProduct()
    {
        return $this->belongsTo(Product::class, 'accessory_product_id');
    }
}
