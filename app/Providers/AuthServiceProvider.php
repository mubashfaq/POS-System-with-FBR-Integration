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
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        UserBusiness::class => UserBusinessPolicy::class
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Gate::define('customer_create', function ($user, $business) {
        //     return currentUserBusiness()
        //         && currentUserBusiness()->hasPermissionTo('customer_create');
        // });

        $permissions = [
        'brand_create',
        'brand_delete',
        'brand_update',
        'brand_view',

        'category_create',
        'category_delete',
        'category_update',
        'category_view',

        'contact_settings',

        'currency_create',
        'currency_delete',
        'currency_set_default',
        'currency_update',
        'currency_view',

        'custom_settings',

        'customer_create',
        'customer_delete',
        'customer_sales',
        'customer_update',
        'customer_view',

        'dashboard_view',

        'invoice_settings',

        'notification_settings',

        'permission_view',

        'product_create',
        'product_delete',
        'product_import',
        'product_purchase',
        'product_update',
        'product_view',

        'purchase_create',
        'purchase_delete',
        'purchase_update',
        'purchase_view',

        'reports_inventory',
        'reports_sales',
        'reports_summary',

        'role_create',
        'role_delete',
        'role_update',
        'role_view',

        'sale_create',
        'sale_delete',
        'sale_edit',
        'sale_update',
        'sale_view',

        'socials_settings',
        'style_settings',

        'supplier_create',
        'supplier_delete',
        'supplier_update',
        'supplier_view',

        'unit_create',
        'unit_delete',
        'unit_update',
        'unit_view',

        'user_create',
        'user_delete',
        'user_suspend',
        'user_update',
        'user_view',

        'website_settings',
        'website_status_settings',
    ];

    foreach ($permissions as $permission) {
        Gate::define($permission, function ($user, $business = null) use ($permission) {
            return currentUserBusiness()
                && currentUserBusiness()->hasPermissionTo($permission);
        });
    }


    }
}
