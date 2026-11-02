<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessCounter extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'last_invoice_number',
        'last_import_number',
    ];
}
