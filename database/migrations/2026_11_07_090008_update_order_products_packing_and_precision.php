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
     * Existing meaning preserved:
     * - quantity: base qty
     * - price: price per base unit
     * - purchase_price: cost per base unit
     */
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            if (!Schema::hasColumn('order_products', 'packing_id')) {
                $table->foreignId('packing_id')->nullable()->after('product_variation_id')->constrained('packings')->nullOnDelete();
            }
            if (!Schema::hasColumn('order_products', 'qty_unit_id')) {
                $table->foreignId('qty_unit_id')->nullable()->after('packing_id')->constrained('units')->nullOnDelete();
            }
            if (!Schema::hasColumn('order_products', 'entered_qty')) {
                $table->decimal('entered_qty', 14, 4)->nullable()->after('qty_unit_id');
            }
            if (!Schema::hasColumn('order_products', 'base_qty_factor')) {
                $table->decimal('base_qty_factor', 14, 4)->default(1.0000)->after('entered_qty');
            }
            if (!Schema::hasColumn('order_products', 'unit_price')) {
                $table->decimal('unit_price', 15, 4)->nullable()->after('base_qty_factor');
            }
        });

        DB::statement("ALTER TABLE order_products MODIFY quantity DECIMAL(14, 4) NOT NULL DEFAULT 1.0000");
        DB::statement("ALTER TABLE order_products MODIFY price DECIMAL(15, 4) NOT NULL DEFAULT 0.0000");
        DB::statement("ALTER TABLE order_products MODIFY purchase_price DECIMAL(15, 4) NOT NULL DEFAULT 0.0000");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $colsToDrop = [];
            if (Schema::hasColumn('order_products', 'qty_unit_id')) {
                $table->dropForeign(['qty_unit_id']);
                $colsToDrop[] = 'qty_unit_id';
            }
            if (Schema::hasColumn('order_products', 'packing_id')) {
                $table->dropForeign(['packing_id']);
                $colsToDrop[] = 'packing_id';
            }
            if (Schema::hasColumn('order_products', 'entered_qty')) {
                $colsToDrop[] = 'entered_qty';
            }
            if (Schema::hasColumn('order_products', 'base_qty_factor')) {
                $colsToDrop[] = 'base_qty_factor';
            }
            if (Schema::hasColumn('order_products', 'unit_price')) {
                $colsToDrop[] = 'unit_price';
            }
            if (!empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });

        DB::statement("ALTER TABLE order_products MODIFY quantity INT NOT NULL DEFAULT 1");
        DB::statement("ALTER TABLE order_products MODIFY price DOUBLE NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE order_products MODIFY purchase_price DOUBLE NOT NULL DEFAULT 0");
    }
};

