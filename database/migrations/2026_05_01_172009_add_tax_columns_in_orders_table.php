<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->string('tax_rate')->nullable()->after('discount')
                  ->comment('Tax rate as stored on the product at time of sale e.g. 18% or 50');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate')
                  ->comment('Computed tax amount for this line item');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_tax', 10, 2)->default(0)->after('discount')
                  ->comment('Sum of all line-level tax amounts for this order');
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_amount']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('total_tax');
        });
    }
};