<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportExpense extends Model
{
    protected $fillable = [
        'import_id',
        'category_id',
        'amount',
        'cbf_cbm'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}