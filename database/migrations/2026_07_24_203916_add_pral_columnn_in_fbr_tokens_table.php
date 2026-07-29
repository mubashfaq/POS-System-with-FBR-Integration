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
        Schema::table('fbr_tokens', function (Blueprint $table) {
            $table->string('pral_sandbox_token')->nullable()->after('digital_production_token');
            $table->string('pral_production_token')->nullable()->after('pral_sandbox_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fbr_tokens', function (Blueprint $table) {
            //
        });
    }
};
