<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->string('hsCode', 255)->nullable()->after('product_id');
            $table->string('uom', 255)->nullable()->after('hsCode');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            //
        });
    }
};
