<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('remaining_qty', 12, 2)->default(0)->after('quantity');
        });

        // Backfill: set remaining_qty = quantity for all existing purchase items
        // This is a safe starting point. In reality, some stock may already be sold,
        // but we set it to the purchased quantity as default.
        DB::table('purchase_items')->update([
            'remaining_qty' => DB::raw('quantity')
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn('remaining_qty');
        });
    }
};
