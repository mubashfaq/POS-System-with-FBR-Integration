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

            // Remove old single tokens if you don’t need them
            $table->dropColumn(['sandbox_token', 'production_token']);

            // Add new structured tokens
            $table->string('pos_sandbox_token')->nullable()->after('business_id');
            $table->string('pos_production_token')->nullable()->after('pos_sandbox_token');

            $table->string('digital_sandbox_token')->nullable()->after('pos_production_token');
            $table->string('digital_production_token')->nullable()->after('digital_sandbox_token');

            $table->tinyInteger('environment')
                  ->default(0)
                  ->comment('0 = sandbox, 1 = production')
                  ->after('digital_production_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
