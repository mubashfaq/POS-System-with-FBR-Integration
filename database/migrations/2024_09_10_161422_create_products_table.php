<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('image')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->text('description')->nullable();
            $table->foreignIdFor(Category::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Brand::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Unit::class)->nullable()->constrained()->nullOnDelete();
            $table->double('price',10,2)->default(0);
            // New fields from your validation
                $table->string('hsCode', 255);
                $table->text('hs_description');
                $table->string('rate_of_tax', 255);
                $table->decimal('salesTaxWithheldAtSource', 10, 2); // numeric with 2 decimals
                $table->string('sale_type', 255);
                $table->string('sro_item', 10)->default("");
                $table->string('uom', 255);
                $table->string('sro_schedule', 255)->default('');

            // End of new fields
            $table->double('discount',10,2)->default(0);
            $table->string('discount_type')->default('fixed');
            $table->double('purchase_price', 10, 2)->default(0);
            $table->integer('quantity')->default(0);
            $table->date('expire_date')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
