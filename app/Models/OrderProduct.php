<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
    use HasFactory;

    protected $guarded = [];
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
}
