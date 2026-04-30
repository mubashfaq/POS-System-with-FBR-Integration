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
        'max_pos_invoices',
        'max_digital_invoices',
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
        'max_pos_invoices'     => 'integer',
        'max_digital_invoices' => 'integer',
        'max_users'    => 'integer',
        // 'max_products' => 'integer',
    ];
 
 
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

     /**
     * Total invoice limit based on business_type:
     *
     *   pos     → max_pos_invoices only
     *   digital → max_digital_invoices only
     *   both    → max_pos_invoices + max_digital_invoices
     *             (if either is -1, combined = -1 / unlimited)
     */
    public function maxInvoicesFor(string $businessType): int
    {
        return match ($businessType) {
            'pos'     => $this->max_pos_invoices,
            'digital' => $this->max_digital_invoices,
            'both'    => $this->combinedInvoiceLimit(),
        };
    }
 
    /**
     * Combined limit for 'both' type businesses.
     * If either side is -1 (unlimited), combined is also unlimited.
     */
    public function combinedInvoiceLimit(): int
    {
        if ($this->max_pos_invoices === -1 || $this->max_digital_invoices === -1) {
            return -1;
        }
 
        return $this->max_pos_invoices + $this->max_digital_invoices;
    }
 
 
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }
 
    public function limitLabel(string $field): string
    {
        return $this->{$field} === -1 ? 'Unlimited' : (string) $this->{$field};
    }
 
    public function invoiceLimitLabel(string $businessType): string
    {
        $limit = $this->maxInvoicesFor($businessType);
        return $limit === -1 ? 'Unlimited' : (string) $limit;
    }
 
 
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}