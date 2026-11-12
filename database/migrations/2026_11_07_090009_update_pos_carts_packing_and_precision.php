<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * PosCart:
     * - quantity stays the base qty
     * - entered_qty = qty typed in the selected unit
     * - entered_unit_price = price per selected unit
     */
    public function up(): void
    {
        Schema::table('pos_carts', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_carts', 'packing_id')) {
                $table->foreignId('packing_id')->nullable()->after('product_variation_id')->constrained('packings')->nullOnDelete();
            }
            if (!Schema::hasColumn('pos_carts', 'qty_unit_id')) {
                $table->foreignId('qty_unit_id')->nullable()->after('packing_id')->constrained('units')->nullOnDelete();
            }
            if (!Schema::hasColumn('pos_carts', 'entered_qty')) {
                $table->decimal('entered_qty', 14, 4)->nullable()->after('qty_unit_id');
            }
            if (!Schema::hasColumn('pos_carts', 'entered_unit_price')) {
                $table->decimal('entered_unit_price', 15, 4)->nullable()->after('entered_qty');
            }
        });

        DB::statement("ALTER TABLE pos_carts MODIFY quantity DECIMAL(14, 4) NOT NULL DEFAULT 1.0000");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_carts', function (Blueprint $table) {
            $colsToDrop = [];
            if (Schema::hasColumn('pos_carts', 'qty_unit_id')) {
                $table->dropForeign(['qty_unit_id']);
                $colsToDrop[] = 'qty_unit_id';
            }
            if (Schema::hasColumn('pos_carts', 'packing_id')) {
                $table->dropForeign(['packing_id']);
                $colsToDrop[] = 'packing_id';
            }
            if (Schema::hasColumn('pos_carts', 'entered_qty')) {
                $colsToDrop[] = 'entered_qty';
            }
            if (Schema::hasColumn('pos_carts', 'entered_unit_price')) {
                $colsToDrop[] = 'entered_unit_price';
            }
            if (!empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });

        DB::statement("ALTER TABLE pos_carts MODIFY quantity INT NOT NULL DEFAULT 1");
    }
};

