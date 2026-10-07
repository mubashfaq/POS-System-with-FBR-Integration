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
        Schema::table('pos_carts', fn(Blueprint $t) =>
            $t->unsignedBigInteger('product_variation_id')->nullable()->after('product_id')->index());

        Schema::table('order_products', fn(Blueprint $t) =>
            $t->unsignedBigInteger('product_variation_id')->nullable()->after('product_id')->index());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_tables', function (Blueprint $table) {
            //
        });
    }
};
