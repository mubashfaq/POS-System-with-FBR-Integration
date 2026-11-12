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
     * Column mappings from now on:
     * - ctn_qty: qty typed in the selected unit
     * - pcs_per_carton: factor snapshot
     * - total_pcs: base qty
     * - price_pc: price per base unit
     * - unit_id: base unit
     * - amount: total_pcs * price_pc
     */
    public function up(): void
    {
        Schema::table('import_products', function (Blueprint $table) {
            if (!Schema::hasColumn('import_products', 'packing_id')) {
                $table->foreignId('packing_id')->nullable()->after('product_variation_id')->constrained('packings')->nullOnDelete();
            }
            if (!Schema::hasColumn('import_products', 'qty_unit_id')) {
                $table->foreignId('qty_unit_id')->nullable()->after('packing_id')->constrained('units')->nullOnDelete();
            }
        });

        DB::statement("ALTER TABLE import_products MODIFY pcs_per_carton DECIMAL(14, 4) NOT NULL DEFAULT 0.0000");
        DB::statement("ALTER TABLE import_products MODIFY ctn_qty DECIMAL(14, 4) NOT NULL DEFAULT 0.0000");
        DB::statement("ALTER TABLE import_products MODIFY total_pcs DECIMAL(16, 4) NOT NULL DEFAULT 0.0000");
        DB::statement("ALTER TABLE import_products MODIFY price_pc DECIMAL(15, 4) NOT NULL DEFAULT 0.0000");

        if (Schema::hasColumn('import_products', 'sale_price')) {
            DB::statement("ALTER TABLE import_products MODIFY sale_price DECIMAL(15, 4) NULL DEFAULT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_products', function (Blueprint $table) {
            if (Schema::hasColumn('import_products', 'qty_unit_id')) {
                $table->dropForeign(['qty_unit_id']);
                $table->dropColumn('qty_unit_id');
            }
            if (Schema::hasColumn('import_products', 'packing_id')) {
                $table->dropForeign(['packing_id']);
                $table->dropColumn('packing_id');
            }
        });

        DB::statement("ALTER TABLE import_products MODIFY pcs_per_carton DECIMAL(15, 2) NOT NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE import_products MODIFY ctn_qty INT UNSIGNED NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE import_products MODIFY total_pcs INT UNSIGNED NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE import_products MODIFY price_pc DECIMAL(15, 2) NOT NULL DEFAULT 0.00");

        if (Schema::hasColumn('import_products', 'sale_price')) {
            DB::statement("ALTER TABLE import_products MODIFY sale_price DECIMAL(15, 2) NULL DEFAULT NULL");
        }
    }
};

