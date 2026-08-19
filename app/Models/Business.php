<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    use HasFactory;
    protected $fillable = [
        'business_name',
        'email',
        'phone',
        'logo','currency','facebook_link','whatsapp_link','youtube_link','instagram_link',
        'industry',
        'ntn',
        'business_type',
        'cnic',
        'state',
        'country',
        'city','address',
        'enable_batch',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_business', 'business_id', 'user_id')
                    ->withTimestamps();
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
                    ->where('status', 'active')
                    ->with('plan')
                    ->latestOfMany();
    }

    /**
     * Shortcut: get the active plan directly from the business.
     */
    public function activePlan(): ?Plan
    {
        return $this->subscription?->plan;
    }

    public function invoiceSetting()
    {
        return $this->hasOne(InvoiceSetting::class, 'business_id');
    }
}
