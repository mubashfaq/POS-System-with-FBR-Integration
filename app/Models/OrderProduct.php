<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'custom_fields' => 'array',
        'quantity' => 'decimal:4',
        'entered_qty' => 'decimal:4',
        'base_qty_factor' => 'decimal:4',
        'price' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'purchase_price' => 'decimal:4',
    ];

    public function packing()
    {
        return $this->belongsTo(Packing::class, 'packing_id');
    }

    public function qtyUnit()
    {
        return $this->belongsTo(Unit::class, 'qty_unit_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    protected $appends = ['discounted_price', 'quantity_text', 'invoice_unit_price'];


    public function getQuantityTextAttribute(): string
    {
        $factor = (float) ($this->base_qty_factor ?? 1);
        if ($this->packing_id && $this->qty_unit_id && $factor > 1) {
            $entered = (float) ($this->entered_qty ?? ($this->quantity / $factor));
            $enteredFmt = $entered == (int) $entered ? (int) $entered : round($entered, 4);

            $qtyUnit = $this->qtyUnit?->title ?? 'Carton';

            return "{$enteredFmt} {$qtyUnit}";
        }

        $baseQty = (float) $this->quantity;
        $baseFmt = $baseQty == (int) $baseQty ? (int) $baseQty : round($baseQty, 4);

        $unit = $this->product?->unit?->title ?? $this->qtyUnit?->title ?? '';

        return trim("{$baseFmt} {$unit}");
    }


    public function getPackingDescriptionAttribute(): ?string
    {
        $factor = (float) ($this->base_qty_factor ?? 1);

        if (!$this->packing_id || !$this->qty_unit_id || $factor <= 1) {
            return null;
        }

        $factorFmt = $factor == (int) $factor ? (int) $factor : round($factor, 4);

        $qtyUnit = $this->qtyUnit?->title ?? 'Carton';
        $baseUnit = $this->product?->unit?->title ?? 'Piece';

        return "1 {$qtyUnit} = {$factorFmt} {$baseUnit}";
    }



    public function getInvoiceUnitPriceAttribute(): float
    {
        if ($this->unit_price !== null && (float) $this->unit_price > 0) {
            return (float) $this->unit_price;
        }

        $factor = (float) ($this->base_qty_factor ?? 1);
        if ($factor > 1 && $this->price !== null) {
            return round((float) $this->price * $factor, 4);
        }

        return (float) ($this->price ?? $this->discounted_price ?? 0);
    }

    public function getDiscountedPriceAttribute()
    {
        $qty = $this->quantity ?: 1;
        $total = $this->total;
        $orderType = $this->order->type ?? null;

        // Use stored tax values if available (new records)
        if (!is_null($this->sale_tax) || !is_null($this->further_tax)) {

            $salesRate = !is_null($this->sale_tax) ? floatval($this->sale_tax) : 0;
            $furtherRate = !is_null($this->further_tax) ? floatval($this->further_tax) : 0;

            $base = $total - ($salesRate + $furtherRate);
        } else {
            // Fallback: calculate from rate fields (old/legacy records)
            if ($orderType === 'pos_invoice') {
                $rateText = trim($this->product->pos_sale_tax);
                $salesRate = floatval(str_replace('%', '', $rateText)) / 100;
                $furtherRate = 0;
            } else {
                $rateText = trim($this->product->rate_of_tax);
                $salesRate = floatval(str_replace('%', '', $rateText)) / 100;

                $furtherRate = 0;

                if (
                    $this->order->customer &&
                    strtolower($this->order->customer->is_registered) === 'unregistered' &&
                    $this->product->sale_type != '3rd Schedule Goods'
                ) {
                    $furtherRate = 0.04;
                }
            }
            // Reverse calculation
            $base = $total / (1 + $salesRate + $furtherRate);
        }



        return number_format($base / $qty, 2);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function purchaseItem()
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    /**
     * Accessory order-product rows that belong to this parent row.
     */
    public function accessoryItems()
    {
        return $this->hasMany(OrderProduct::class, 'parent_order_product_id');
    }

    /**
     * The parent order-product row (when this row is an accessory).
     */
    public function parentOrderProduct()
    {
        return $this->belongsTo(OrderProduct::class, 'parent_order_product_id');
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id')->withTrashed();
    }
}
