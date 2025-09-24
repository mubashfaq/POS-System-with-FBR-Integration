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
        Schema::create('invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');

            $table->boolean('logo')->default(false);
            $table->boolean('site_name')->default(false);
            $table->boolean('phone')->default(false);
            $table->boolean('email')->default(false);
            $table->boolean('address')->default(false);
            $table->boolean('customer')->default(false);

            // text / string fields
            $table->text('note_to_customer')->nullable();
            $table->string('post_invoice_width')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_settings');
    }
};
