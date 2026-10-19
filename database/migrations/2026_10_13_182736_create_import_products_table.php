<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();

            $table->decimal('pcs_per_carton', 15, 2)->default(0);
            $table->unsignedInteger('ctn_qty')->default(0);
            $table->unsignedInteger('total_pcs')->default(0);
            $table->decimal('price_pc', 15, 2)->default(0);
            $table->decimal('price_dz', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_products');
    }
};
