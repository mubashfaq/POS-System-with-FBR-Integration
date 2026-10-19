<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_expenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();

            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();

            $table->decimal('amount', 15, 2)->default(0);

            $table->timestamps();

            $table->unique(
                ['import_id', 'category_id'],
                'import_expenses_import_category_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_expenses');
    }
};