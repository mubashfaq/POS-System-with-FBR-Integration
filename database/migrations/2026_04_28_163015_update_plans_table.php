<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace max_invoices with separate pos and digital invoice limits.
     * max_invoices is kept as a virtual/computed concept in the service layer.
     *
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Add new columns
            $table->integer('max_pos_invoices')->default(0)->after('max_invoices');
            $table->integer('max_digital_invoices')->default(0)->after('max_pos_invoices');
        });

        // Migrate existing max_invoices data:
        // Split evenly between pos and digital (adjust manually after migration if needed)
        DB::table('plans')->get()->each(function ($plan) {
            $half = intdiv($plan->max_invoices, 2);
            DB::table('plans')->where('id', $plan->id)->update([
                'max_pos_invoices'     => $plan->max_invoices === -1 ? -1 : $half,
                'max_digital_invoices' => $plan->max_invoices === -1 ? -1 : ($plan->max_invoices - $half),
            ]);
        });

        // Drop old column
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_invoices');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('max_invoices')->default(0)->after('slug');
        });

        // Restore max_invoices as sum of both
        DB::table('plans')->get()->each(function ($plan) {
            $sum = ($plan->max_pos_invoices === -1 || $plan->max_digital_invoices === -1)
                ? -1
                : $plan->max_pos_invoices + $plan->max_digital_invoices;

            DB::table('plans')->where('id', $plan->id)->update([
                'max_invoices' => $sum,
            ]);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['max_pos_invoices', 'max_digital_invoices']);
        });
    }
};