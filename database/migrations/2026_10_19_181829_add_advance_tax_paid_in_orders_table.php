<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('paid_with_advance_tax', 15, 2)->default(0)->after('total_with_advance_tax');
            $table->decimal('due_with_advance_tax', 15, 2)->default(0)->after('paid_with_advance_tax');
        });

        DB::statement("UPDATE orders SET paid_with_advance_tax = COALESCE(paid, 0)");
        DB::statement("UPDATE orders SET due_with_advance_tax = total_with_advance_tax - COALESCE(paid, 0)");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['paid_with_advance_tax', 'due_with_advance_tax']);
        });
    }
};