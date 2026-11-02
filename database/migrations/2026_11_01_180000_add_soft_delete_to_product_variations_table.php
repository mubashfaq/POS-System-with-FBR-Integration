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
        // 1. Add deleted_at for soft deletes
        Schema::table('product_variations', function (Blueprint $table) {
            $table->softDeletes();
        });

        // 2. Change FK on product_variations: cascade → restrict
        //    so product deletion is handled in app code (soft-delete variations first)
        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->restrictOnDelete();
        });

        // 3. Change FK on product_variation_values: cascade → restrict
        //    so soft-deleted variations keep their attribute values for historical display
        Schema::table('product_variation_values', function (Blueprint $table) {
            $table->dropForeign(['product_variation_id']);
            $table->foreign('product_variation_id')
                ->references('id')
                ->on('product_variations')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore original cascade FK on product_variation_values
        Schema::table('product_variation_values', function (Blueprint $table) {
            $table->dropForeign(['product_variation_id']);
            $table->foreign('product_variation_id')
                ->references('id')
                ->on('product_variations')
                ->cascadeOnDelete();
        });

        // Restore original cascade FK on product_variations
        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
