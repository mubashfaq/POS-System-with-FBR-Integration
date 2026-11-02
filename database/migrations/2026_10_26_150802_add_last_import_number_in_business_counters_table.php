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
        Schema::table('business_counters', function (Blueprint $table) {
            $table->unsignedBigInteger('last_import_number')->default(0)->after('last_invoice_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_counters', function (Blueprint $table) {
            $table->dropColumn('last_import_number');
        });
    }
};
