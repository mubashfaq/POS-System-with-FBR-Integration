<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_customer_tax', 10, 2)
                  ->default(0)
                  ->after('total_sale_tax');
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->decimal('customer_tax', 10, 2)
                  ->default(0)
                  ->after('sale_tax');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
};
