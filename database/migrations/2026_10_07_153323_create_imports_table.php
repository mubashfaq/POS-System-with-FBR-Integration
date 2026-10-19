<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index(); // <-- ADDED
            $table->string('import_no');
            $table->string('container_code')->nullable();
            $table->date('p_date')->nullable();
            $table->date('clear_date')->nullable();
            $table->string('currency')->default('USD');
            $table->decimal('ex_rate', 15, 2)->default(0);
            $table->decimal('inv_amount', 15, 2)->default(0);
            $table->decimal('bo_expense', 15, 2)->default(0);
            $table->decimal('bo_cbf_cbm', 15, 2)->default(0);
            $table->decimal('nbo_exp', 15, 2)->default(0);
            $table->decimal('nbo_cbf_cbm', 15, 2)->default(0);
            $table->decimal('lt_expense', 15, 2)->default(0);
            $table->decimal('lt_cbf_cbm', 15, 2)->default(0);
            $table->decimal('total_cbf_cbm', 15, 2)->default(0);
            $table->decimal('total_expense_1', 15, 2)->default(0);
            $table->decimal('inv_amount_pkr', 15, 2)->default(0);
            $table->string('godown')->nullable();
            $table->string('supplier')->nullable();
            $table->text('address')->nullable();
            $table->decimal('freight_expense', 15, 2)->default(0);
            $table->decimal('fta_expense', 15, 2)->default(0);
            $table->decimal('fitting_expense', 15, 2)->default(0);
            $table->decimal('co_expense', 15, 2)->default(0);
            $table->decimal('other_expense', 15, 2)->default(0);
            $table->decimal('total_expense_2', 15, 2)->default(0);
            $table->timestamps();

            // Optional: unique import_no per business
            $table->unique(['business_id', 'import_no']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('imports');
    }
};