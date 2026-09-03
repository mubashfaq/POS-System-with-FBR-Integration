<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartSetting extends Model
{
    use HasFactory;
    protected $fillable = [
        'business_id',
        'draft_btn',
        'custom_field_btn',
    ];

    protected $casts = [
        'draft_btn' => 'boolean',
        'custom_field_btn' => 'boolean',
    ];
}
