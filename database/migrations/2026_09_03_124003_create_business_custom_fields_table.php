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
        // create_business_custom_fields_table
        Schema::create('business_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('field_name');   // slug, e.g. color
            $table->string('field_label');  // Display label, e.g. Color
            $table->string('field_type')->default('text'); // text, number, select
            $table->json('field_options')->nullable();      // for select type
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('business_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_custom_fields');
    }
};
