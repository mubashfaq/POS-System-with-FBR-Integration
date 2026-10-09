<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;

use App\Models\UserBusiness;
use App\Policies\UserBusinessPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     */
    protected $policies = [
        UserBusiness::class => UserBusinessPolicy::class
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {

        // Register one Gate per permission, all delegating to the
        // current UserBusiness's Spatie permissions.
        foreach (config('permissions.list') as $permission) {
            Gate::define($permission, function ($user) use ($permission) {
                $business = currentUserBusiness();

                return $business !== null && $business->hasPermissionTo($permission);
            });
        }

    }
}
