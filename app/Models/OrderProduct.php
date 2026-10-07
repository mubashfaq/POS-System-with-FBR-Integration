<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['custom_fields' => 'array'];
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    protected $appends = ['discounted_price'];

    public function getDiscountedPriceAttribute()
    {
        $qty = $this->quantity ?: 1;
        $total = $this->total;
        $orderType = $this->order->type ?? null;

        // Use stored tax values if available (new records)
        if (!is_null($this->sale_tax) || !is_null($this->further_tax)) {

            $salesRate = !is_null($this->sale_tax) ? floatval($this->sale_tax) : 0;
            $furtherRate = !is_null($this->further_tax) ? floatval($this->further_tax) : 0;
            // $customerRate = !is_null($this->customer_tax) ? floatval($this->customer_tax) : 0;

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
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }
}
