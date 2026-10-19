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
        Schema::table('imports', function (Blueprint $table) {
            $table->foreignId('purchase_id')->nullable()->after('supplier_id')
                ->constrained('purchases')->nullOnDelete();
        });
        Schema::table('import_products', function (Blueprint $table) {
            $table->foreignId('product_variation_id')->nullable()->after('product_id')
                ->constrained('product_variations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_products', function (Blueprint $table) {
            //
        });
    }
};
