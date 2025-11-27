<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SroItemCode extends Model
{
    use HasFactory;
    protected $fillable = [
        'srO_ITEM_ID',
        'srO_ITEM_DESC',
    ];
}
