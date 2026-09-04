<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessCustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'field_name',
        'field_label',
        'field_type',
        'field_options',
        'sort_order',
        'is_active',
    ];
    protected $casts = ['field_options' => 'array', 'is_active' => 'boolean',];
}
