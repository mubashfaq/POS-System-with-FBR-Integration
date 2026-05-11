<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // STEP 1: Rename column FIRST
        Schema::table('order_products', function (Blueprint $table) {
            $table->renameColumn('tax_amount', 'sale_tax');
        });

        // STEP 2: Add new column AFTER rename is done
        Schema::table('order_products', function (Blueprint $table) {
            $table->decimal('further_tax', 10, 2)
                  ->default(0)
                  ->after('sale_tax');
        });

        // Orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('total_tax', 'total_sale_tax');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_further_tax', 10, 2)
                  ->default(0)
                  ->after('total_sale_tax');
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->renameColumn('sale_tax', 'tax_amount');
            $table->dropColumn('further_tax');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('total_sale_tax', 'total_tax');
            $table->dropColumn('total_further_tax');
        });
    }
};