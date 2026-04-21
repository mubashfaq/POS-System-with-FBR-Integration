<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'         => 'Basic',
                'slug'         => 'basic',
                'price'        => 999.99,
                'max_invoices' => 5,
                'max_users'    => 1,
                // 'max_products' => 20,
                // 'features'     => json_encode([]),
                'is_active'    => true,
                'sort_order'   => 1,
            ],
            [
                'name'         => 'Standard',
                'slug'         => 'standard',
                'price'        => 1999.99,
                'max_invoices' => 20,
                'max_users'    => 5,
                // 'max_products' => 200,
                // 'features'     => json_encode(['reports']),
                'is_active'    => true,
                'sort_order'   => 2,
            ],
            [
                'name'         => 'Pro',
                'slug'         => 'pro',
                'price'        => 4499.99,
                'max_invoices' => -1,       // unlimited
                'max_users'    => -1,       // unlimited
                // 'max_products' => -1,       // unlimited
                // 'features'     => json_encode(['reports', 'exports', 'barcode']),
                'is_active'    => true,
                'sort_order'   => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }

        $this->command->info('Plans seeded: Basic, Standard, Pro');
    }
}