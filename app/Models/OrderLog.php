<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'user_id',
        'order_id',
        'request_payload',
        'response',
        'status_code',
        'error_code',
        'error_message',
        'success',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response' => 'array',
        'success' => 'boolean',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

