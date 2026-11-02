<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Add currency_id (nullable — currency is optional)
        Schema::table('imports', function (Blueprint $table) {
            $table->unsignedBigInteger('currency_id')->nullable()->after('clear_date');
        });

        // 2. Backfill from the old currency string where possible
        DB::statement("
            UPDATE imports i
            INNER JOIN currencies c ON UPPER(i.currency) = UPPER(c.code)
            SET i.currency_id = c.id
            WHERE i.currency_id IS NULL
              AND i.currency IS NOT NULL
        ");

        // 3. FK with set null (safe: deleting a currency just clears the link)
        Schema::table('imports', function (Blueprint $table) {
            $table->foreign('currency_id')
                ->references('id')
                ->on('currencies')
                ->onDelete('set null');
        });

        // 4. Drop the redundant currency string
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        // 5. Drop unused column
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn('total_expense_1');
        });

        // 6. Rename total_expense_2 → total_expense
        Schema::table('imports', function (Blueprint $table) {
            $table->renameColumn('total_expense_2', 'total_expense');
        });
    }

    public function down(): void
    {
        // 6. Rename back
        Schema::table('imports', function (Blueprint $table) {
            $table->renameColumn('total_expense', 'total_expense_2');
        });

        // 5. Re-add total_expense_1
        Schema::table('imports', function (Blueprint $table) {
            $table->decimal('total_expense_1', 15, 2)->nullable()->after('inv_amount');
        });

        // 4. Re-add currency string
        Schema::table('imports', function (Blueprint $table) {
            $table->string('currency', 10)->nullable()->after('clear_date');
        });

        // Restore currency string from relation
        DB::statement("
            UPDATE imports i
            INNER JOIN currencies c ON i.currency_id = c.id
            SET i.currency = c.code
        ");

        // 3. Drop FK
        Schema::table('imports', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
        });

        // 1. Drop currency_id
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn('currency_id');
        });
    }
};