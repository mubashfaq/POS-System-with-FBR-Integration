<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'price',
        'max_invoices',
        'max_users',
        // 'max_products',
        'features',
        'is_active',
        'sort_order',
    ];
 
    protected $casts = [
        'features'     => 'array',
        'price'        => 'decimal:2',
        'is_active'    => 'boolean',
        'max_invoices' => 'integer',
        'max_users'    => 'integer',
        // 'max_products' => 'integer',
    ];
 
    // ─── Relationships ───────────────────────────────────────────────────────
 
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
 
    // ─── Helpers ─────────────────────────────────────────────────────────────
 
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }
 
    public function isUnlimited(string $field): bool
    {
        return $this->{$field} === -1;
    }
 
    /**
     * Human-readable limit: returns "Unlimited" or the number.
     */
    public function limitLabel(string $field): string
    {
        return $this->{$field} === -1 ? 'Unlimited' : (string) $this->{$field};
    }
 
    // ─── Scopes ──────────────────────────────────────────────────────────────
 
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}