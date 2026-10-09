<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'import_no',
        'container_code',
        'p_date',
        'clear_date',
        'currency',
        'ex_rate',
        'inv_amount',
        'total_expense_1',
        'inv_amount_pkr',
        'godown_id',
        'supplier_id',
        'address',
        'freight_expense',
        'fta_expense',
        'fitting_expense',
        'co_expense',
        'other_expense',
        'total_expense_2',
    ];

    protected $casts = [
        'p_date' => 'date',
        'clear_date' => 'date',
    ];

    public function scopeForCurrentBusiness($query)
    {
        return $query->where('business_id', session('business_id'));
    }

    protected static function booted()
    {
        static::addGlobalScope('business', function ($query) {
            if (session()->has('business_id')) {
                $query->where('business_id', session('business_id'));
            }
        });
    }

    public function godown()
    {
        return $this->belongsTo(Godown::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ImportExpense::class);
    }
}