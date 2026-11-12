<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackingLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'packing_id',
        'level',
        'unit_id',
        'contains_qty',
        'base_qty',
    ];

    protected $casts = [
        'level' => 'integer',
        'contains_qty' => 'decimal:4',
        'base_qty' => 'decimal:4',
    ];

    public function packing()
    {
        return $this->belongsTo(Packing::class, 'packing_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}

