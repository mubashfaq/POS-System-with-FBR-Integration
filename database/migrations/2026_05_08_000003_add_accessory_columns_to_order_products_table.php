<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->boolean('is_accessory')->default(false)->after('purchase_item_id');
            $table->unsignedBigInteger('parent_order_product_id')->nullable()->after('is_accessory');
            $table->foreign('parent_order_product_id')
                ->references('id')
                ->on('order_products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropForeign(['parent_order_product_id']);
            $table->dropColumn(['is_accessory', 'parent_order_product_id']);
        });
    }
};
