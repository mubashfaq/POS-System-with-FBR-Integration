<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_with_advance_tax', 15, 2)->default(0)->after('total');
        });

        // Update previous records
        DB::statement("UPDATE orders SET total_with_advance_tax = total + COALESCE(total_customer_tax, 0)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('total_with_advance_tax');
        });
    }
};
