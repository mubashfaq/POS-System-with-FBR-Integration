<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use function PHPSTORM_META\map;

class Order extends Model
{
    use HasFactory;

    protected $fillable=['business_id', 'invoice_number', 'custom_invoice_number', 'po_number', 'user_id', 'customer_id', 'discount', 'total_sale_tax', 'total_customer_tax', 'total_further_tax', 'sub_total','total','paid','due','note', 'order_date', 'is_retured','status', 'order_stage', 'is_cancelled', 'cancelled_by', 'cancelled_at', 'api_status','error_code','error_message','api_message', 'fbr_invoice_no', 'invoice_environment', 'type'];

    protected $guarded = [];
    protected $appends = ['total_item'];
    public function products()
    {
        return $this->hasMany(OrderProduct::class);
    }
    public function transactions()
    {
        return $this->hasMany(OrderTransaction::class);
    }
    public function customer(){
        return $this->belongsTo(Customer::class);
    }
    public function getTotalItemAttribute()
    {
        $value = $this->products()
            ->where('is_accessory', 0)
            ->sum('quantity');

        return $value == (int) $value
            ? (string)(int) $value
            : number_format($value, 2, '.', '');
    }
    
    
    
    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function getDisplayInvoiceNumberAttribute()
    {
        if (
            $this->business?->invoiceSetting?->invoice_number == 1 &&
            filled($this->custom_invoice_number)
        ) {
            return $this->custom_invoice_number;
        }

        return 'INV-' . $this->business_id . '-' . str_pad($this->invoice_number, 4, '0', STR_PAD_LEFT);
    }

    protected $casts = [
        'order_date' => 'date',
    ];
    
   
}
