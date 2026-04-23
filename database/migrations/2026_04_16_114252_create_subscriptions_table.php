<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();

            // Billing period window
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');

            // Resets on: new month OR plan upgrade
            // Invoices created BEFORE this timestamp are not counted toward current limit
            $table->timestamp('usage_reset_at');

            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();      // null = no expiry (lifetime/manual)

            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};