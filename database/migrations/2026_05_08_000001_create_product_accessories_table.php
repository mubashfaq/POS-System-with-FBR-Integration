<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_accessories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('cascade');
            $table->foreignId('accessory_product_id')
                ->constrained('products')
                ->onDelete('cascade');
            // How many units of accessory per 1 unit of parent product
            $table->decimal('quantity', 10, 2)->default(1);
            $table->timestamps();

            $table->unique(['product_id', 'accessory_product_id'], 'product_accessory_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_accessories');
    }
};
