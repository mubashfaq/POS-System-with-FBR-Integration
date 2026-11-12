<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'short_name', 'is_active',];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function importProducts()
    {
        return $this->hasMany(ImportProduct::class, 'unit_id');
    }

    public function packings()
    {
        return $this->hasMany(Packing::class, 'base_unit_id');
    }

    public function packingLevels()
    {
        return $this->hasMany(PackingLevel::class, 'unit_id');
    }
}
