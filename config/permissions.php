<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Master Permission List
    |--------------------------------------------------------------------------
    |
    | Every permission in the app. Used by RolePermissionSeeder (to create
    | Spatie permissions) and AuthServiceProvider (to register Gates).
    | Add new permissions here only — no other file needs editing.
    |
    */

    'list' => [
        'dashboard_view',

        'customer_create',
        'customer_view',
        'customer_update',
        'customer_delete',
        'customer_sales',

        'supplier_view',
        'supplier_create',
        'supplier_update',
        'supplier_delete',

        'product_create',
        'product_view',
        'product_update',
        'product_delete',
        'product_import',
        'product_variation',

        'brand_create',
        'brand_view',
        'brand_update',
        'brand_delete',
        'category_create',
        'category_view',
        'category_update',
        'category_delete',
        'unit_create',
        'unit_view',
        'unit_update',
        'unit_delete',

        'sale_create',
        'sale_view',
        'sale_update',
        'sale_delete',

        'purchase_create',
        'purchase_view',
        'purchase_update',
        'purchase_delete',

        'reports_summary',
        'reports_sales',
        'reports_inventory',

        'currency_create',
        'currency_view',
        'currency_update',
        'currency_delete',
        'currency_set_default',

        'role_create',
        'role_view',
        'role_update',
        'role_delete',
        'permission_view',

        'user_create',
        'user_view',
        'user_update',
        'user_delete',
        'user_suspend',

        'website_settings',
        'contact_settings',
        'socials_settings',
        'style_settings',
        'custom_settings',
        'notification_settings',
        'website_status_settings',
        'invoice_settings',

        'business_update',

        'plan_view',
        'subscription_view',

        'order_logs_view',
        'product_logs_view',

        'import_create',
        'import_view',
        'import_update',
        'import_delete',
        'import_godown',
    ],

    /*
    |--------------------------------------------------------------------------
    | Role → Permission Map
    |--------------------------------------------------------------------------
    |
    | Permissions granted to each non-admin role. Admin gets everything in
    | 'list' automatically. super_admin is intentionally NOT listed — it is
    | a developer-only role created directly in the DB.
    |
    */

    'roles' => [
        'cashier' => [
            'sale_create',
            'sale_view',
            'customer_view',
            'product_create',
            'product_view',
            'product_update',
            'product_delete',
            'product_import',
        ],

        'sales_associate' => [
            'sale_create',
            'sale_view',
        ],
    ],
];