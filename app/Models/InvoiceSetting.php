<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceSetting extends Model
{
    use HasFactory;
    protected $fillable=['business_id','logo','site_name','phone','email','address','customer','note_to_customer','note_msg','post_invoice_width'];
}
