<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_id',
        'product_id',
        'product_variation_id',
        'packing_id',
        'qty_unit_id',
        'unit_id',
        'pcs_per_carton',
        'ctn_qty',
        'total_pcs',
        'price_pc',
        'sale_price',
        'sale_percent',
        'amount',
    ];

    protected $casts = [
        'pcs_per_carton' => 'decimal:4',
        'ctn_qty' => 'decimal:4',
        'total_pcs' => 'decimal:4',
        'price_pc' => 'decimal:4',
        'sale_price' => 'decimal:4',
        'amount' => 'decimal:4',
    ];

    public function import()
    {
        return $this->belongsTo(Import::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function packing()
    {
        return $this->belongsTo(Packing::class, 'packing_id');
    }

    public function qtyUnit()
    {
        return $this->belongsTo(Unit::class, 'qty_unit_id');
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id')->withTrashed();
    }
}
