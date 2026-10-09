<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


class Godown extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'location',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public static function generateSlug(string $name): string
    {
        return Str::slug($name);
    }
}
