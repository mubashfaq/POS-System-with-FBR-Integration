<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->json('request_payload')->nullable();  // FBR payload sent
            $table->json('response')->nullable();         // FBR response received
            $table->integer('status_code')->nullable();   // HTTP status
            $table->string('error_code')->nullable();
            $table->string('error_message')->nullable();
            $table->boolean('success')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_logs');
    }
};

