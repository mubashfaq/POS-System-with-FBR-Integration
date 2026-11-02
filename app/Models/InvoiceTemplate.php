<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTemplate extends Model
{
    protected $fillable = [
        'key',
        'name',
        'view',
        'image',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Only templates that should appear in the picker.
     */
    public static function selectable()
    {
        return static::where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * The default template (fallback).
     */
    public static function getDefault()
    {
        return static::where('is_default', 1)->first()
            ?? static::where('is_active', 1)->orderBy('sort_order')->first();
    }

    /**
     * Find by key.
     */
    public static function findByKey(string $key): ?self
    {
        return static::where('key', $key)->where('is_active', 1)->first();
    }

    protected static function booted(): void
    {
        static::saved(function (self $template) {
            if ($template->is_default) {
                static::where('id', '!=', $template->id)->update(['is_default' => 0]);
            }
        });
    }
}