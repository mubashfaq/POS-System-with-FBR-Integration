<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->string('container_code')->nullable()->change();
            $table->date('p_date')->nullable()->change();
            $table->date('clear_date')->nullable()->change();
            $table->string('currency')->nullable()->change();
            $table->decimal('ex_rate', 15, 2)->nullable()->change();
            $table->decimal('inv_amount', 15, 2)->nullable()->change();
            $table->decimal('bo_cbf_cbm', 15, 2)->nullable()->change();
            $table->decimal('nbo_cbf_cbm', 15, 2)->nullable()->change();
            $table->decimal('lt_cbf_cbm', 15, 2)->nullable()->change();
            $table->decimal('total_cbf_cbm', 15, 2)->nullable()->change();
            $table->decimal('total_expense_1', 15, 2)->nullable()->change();
            $table->decimal('inv_amount_pkr', 15, 2)->nullable()->change();
            $table->foreignId('godown_id')->nullable()->after('inv_amount_pkr')->constrained('godowns')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('godown_id')->constrained('suppliers')->nullOnDelete();
            $table->text('address')->nullable()->change();
            $table->decimal('freight_expense', 15, 2)->nullable()->change();
            $table->decimal('fta_expense', 15, 2)->nullable()->change();
            $table->decimal('fitting_expense', 15, 2)->nullable()->change();
            $table->decimal('co_expense', 15, 2)->nullable()->change();
            $table->decimal('other_expense', 15, 2)->nullable()->change();
            $table->decimal('total_expense_2', 15, 2)->nullable()->change();

            $table->dropColumn(['bo_expense', 'nbo_exp', 'lt_expense', 'godown', 'supplier',]);
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
