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
        Schema::table('import_expenses', function (Blueprint $table) {
            $table->decimal('cbf_cbm', 15, 2)->nullable()->after('amount');
        });

        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn(['bo_cbf_cbm', 'nbo_cbf_cbm', 'lt_cbf_cbm', 'total_cbf_cbm']);
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
