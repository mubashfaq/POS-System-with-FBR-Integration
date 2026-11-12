<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Packing extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'base_unit_id',
        'levels_count',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'levels_count' => 'integer',
    ];

    public function levels()
    {
        return $this->hasMany(PackingLevel::class)->orderBy('level', 'asc');
    }

    public function baseUnit()
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'default_packing_id');
    }

    public function getSummaryAttribute(): string
    {
        $levels = $this->levels()->with('unit')->get();

        if ($levels->isEmpty()) {
            return $this->name ?? '';
        }

        $topBaseQty = (float) ($levels->first()->base_qty ?? 1);
        if ($topBaseQty <= 0) {
            $topBaseQty = 1;
        }

        $parts = [];
        foreach ($levels as $lvl) {
            $baseQty = (float) $lvl->base_qty;
            $ratio = $baseQty > 0 ? ($topBaseQty / $baseQty) : 0;
            $formattedRatio = rtrim(rtrim(number_format($ratio, 4, '.', ''), '0'), '.');
            if ($formattedRatio === '') {
                $formattedRatio = '0';
            }
            $unitTitle = $lvl->unit?->title ?? 'Unit';
            $parts[] = "{$formattedRatio} {$unitTitle}";
        }

        return implode(' = ', $parts);
    }
}

