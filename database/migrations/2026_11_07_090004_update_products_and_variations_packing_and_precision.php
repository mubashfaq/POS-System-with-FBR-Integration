<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'default_packing_id')) {
                $table->foreignId('default_packing_id')
                    ->nullable()
                    ->after('unit_id')
                    ->constrained('packings')
                    ->nullOnDelete();
            }
        });

        // Use raw ALTER TABLE MODIFY because doctrine/dbal is not installed
        DB::statement("ALTER TABLE products MODIFY price DECIMAL(15, 4) NOT NULL DEFAULT 0.0000");
        DB::statement("ALTER TABLE products MODIFY purchase_price DECIMAL(15, 4) NOT NULL DEFAULT 0.0000");

        if (Schema::hasTable('product_variations')) {
            DB::statement("ALTER TABLE product_variations MODIFY price DECIMAL(15, 4) NOT NULL DEFAULT 0.0000");
            DB::statement("ALTER TABLE product_variations MODIFY purchase_price DECIMAL(15, 4) NOT NULL DEFAULT 0.0000");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'default_packing_id')) {
                $table->dropForeign(['default_packing_id']);
                $table->dropColumn('default_packing_id');
            }
        });

        DB::statement("ALTER TABLE products MODIFY price DOUBLE(10, 2) NOT NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE products MODIFY purchase_price DOUBLE(10, 2) NOT NULL DEFAULT 0.00");

        if (Schema::hasTable('product_variations')) {
            DB::statement("ALTER TABLE product_variations MODIFY price DECIMAL(15, 2) NOT NULL DEFAULT 0.00");
            DB::statement("ALTER TABLE product_variations MODIFY purchase_price DECIMAL(15, 2) NOT NULL DEFAULT 0.00");
        }
    }
};

