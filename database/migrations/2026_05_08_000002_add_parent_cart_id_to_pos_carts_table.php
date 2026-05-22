<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_carts', function (Blueprint $table) {
            // References the parent cart row when this item is an accessory
            $table->unsignedBigInteger('parent_cart_id')->nullable()->after('purchase_item_id');
            $table->foreign('parent_cart_id')
                ->references('id')
                ->on('pos_carts')
                ->nullOnDelete();

            // Default accessory quantity per parent unit (stored for reference)
            $table->decimal('accessory_default_qty', 10, 2)->nullable()->after('parent_cart_id');
        });
    }

    public function down(): void
    {
        Schema::table('pos_carts', function (Blueprint $table) {
            $table->dropForeign(['parent_cart_id']);
            $table->dropColumn(['parent_cart_id', 'accessory_default_qty']);
        });
    }
};
