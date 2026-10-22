<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;
    protected $fillable = ['title', 'short_name'];

    public function importProducts()
    {
        return $this->hasMany(ImportProduct::class, 'unit_id');
    }
}
