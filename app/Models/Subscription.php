<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Subscription extends Model
{
    protected $fillable = [
        'business_id',
        'plan_id',
        'current_period_start',
        'current_period_end',
        'usage_reset_at',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end'   => 'datetime',
        'usage_reset_at'       => 'datetime',
        'starts_at'            => 'datetime',
        'ends_at'              => 'datetime',
    ];

    // Relationships

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // Key Logic

    /**
     * The point from which invoices are counted.
     * Returns whichever is more recent: period start OR last usage reset (upgrade).
     *
     * Normal month:  count from current_period_start
     * After upgrade: count from usage_reset_at (fresh start for new plan)
     */
    public function countFrom(): Carbon
    {
        return $this->usage_reset_at->gt($this->current_period_start)
            ? $this->usage_reset_at
            : $this->current_period_start;
    }

    /**
     * Days remaining in current billing period.
     */
    public function daysRemaining(): int
    {
        return (int) now()->diffInDays($this->current_period_end, false);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDueForRenewal($query)
    {
        return $query->where('status', 'active')
                     ->where('current_period_end', '<=', now());
    }
}