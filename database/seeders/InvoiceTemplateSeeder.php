<?php

namespace Database\Seeders;

use App\Models\InvoiceTemplate;
use Illuminate\Database\Seeder;

class InvoiceTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'key' => 'default',
                'name' => 'Template 01',
                'view' => 'backend.orders.invoice-templates.default',
                'image' => 'assets/images/invoice-templates/default.png',
                'is_active' => 1,
                'is_default' => 1,
                'sort_order' => 1,
            ],
            [
                'key' => 'modern',
                'name' => 'Template 02',
                'view' => 'backend.orders.invoice-templates.template_02',
                'image' => 'assets/images/invoice-templates/template_02.png',
                'is_active' => 1,
                'is_default' => 0,
                'sort_order' => 2,
            ],
        ];

        foreach ($rows as $row) {
            InvoiceTemplate::updateOrCreate(['key' => $row['key']], $row);
        }
    }
}