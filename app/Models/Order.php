<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use function PHPSTORM_META\map;

class Order extends Model
{
    use HasFactory;

    protected $fillable=['business_id', 'invoice_number', 'user_id','customer_id','discount', 'total_sale_tax', 'total_customer_tax', 'total_further_tax', 'sub_total','total','paid','due','note', 'order_date', 'is_retured','status','api_status','error_code','error_message','api_message', 'fbr_invoice_no'];

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
    
   
}
