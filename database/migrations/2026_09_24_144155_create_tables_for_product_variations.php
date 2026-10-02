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
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('name');
            $table->timestamps();

            $table->unique(['business_id', 'name']);
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')
                ->constrained('attributes')
                ->cascadeOnDelete();

            $table->string('value');
            $table->timestamps();

            $table->unique(['attribute_id', 'value']);
        });

        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('business_id')->index();

            $table->string('sku')->nullable();

            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('price', 15, 2)->default(0);

            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->unique(['business_id', 'sku']);
        });

        Schema::create('product_variation_values', function (Blueprint $table) {
            $table->foreignId('product_variation_id')
                ->constrained('product_variations')
                ->cascadeOnDelete();

            $table->foreignId('attribute_value_id')
                ->constrained('attribute_values')
                ->cascadeOnDelete();

            $table->primary([
                'product_variation_id',
                'attribute_value_id'
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('has_variations')
                ->default(false)
                ->after('quantity');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_variation_id')
                ->nullable()
                ->after('product_id')
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn('product_variation_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('has_variations');
        });

        Schema::dropIfExists('product_variation_values');
        Schema::dropIfExists('product_variations');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
