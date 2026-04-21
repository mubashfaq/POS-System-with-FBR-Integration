<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // Basic, Standard, Pro
            $table->string('slug')->unique();              // basic, standard, pro
            $table->decimal('price', 8, 2)->default(0);
            $table->integer('max_invoices');               // per month | -1 = unlimited
            $table->integer('max_users');                  // total users | -1 = unlimited
            // $table->integer('max_products');               // total products | -1 = unlimited
            $table->json('features')->nullable();          // ["reports","exports","barcode"]
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);     // for display ordering
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};