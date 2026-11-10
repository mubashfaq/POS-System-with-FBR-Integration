<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceSetting extends Model
{
    use HasFactory;
    protected $fillable = [
        'business_id',
        'template',
        'logo',
        'site_name',
        'phone',
        'email',
        'address',
        'customer',
        'sale_tax_number',
        'unit_price',
        'invoice_number',
        'po_number',
        'date_on_top',
        'imc_logo',
        'thanks_for_shopping',
        'note_to_customer',
        'note_msg',
        'post_invoice_width',
    ];
}
