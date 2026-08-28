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
            $table->string('POSID_sandbox')->nullable()->after('pral_production_token');
            $table->string('POSID_production')->nullable()->after('POSID_sandbox');
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
