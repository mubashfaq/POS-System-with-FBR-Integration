<?php

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
        Schema::create('packing_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packing_id')->constrained('packings')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('contains_qty', 12, 4)->default(1);
            $table->decimal('base_qty', 14, 4);
            $table->timestamps();

            $table->unique(['packing_id', 'level']);
            $table->unique(['packing_id', 'unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packing_levels');
    }
};

