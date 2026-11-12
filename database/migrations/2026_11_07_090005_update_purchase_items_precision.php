<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE purchase_items MODIFY quantity DECIMAL(14, 4) NOT NULL DEFAULT 1.0000");
        DB::statement("ALTER TABLE purchase_items MODIFY remaining_qty DECIMAL(14, 4) NOT NULL DEFAULT 0.0000");
        DB::statement("ALTER TABLE purchase_items MODIFY price DECIMAL(15, 4) NOT NULL DEFAULT 0.0000");
        DB::statement("ALTER TABLE purchase_items MODIFY purchase_price DECIMAL(15, 4) NOT NULL DEFAULT 0.0000");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE purchase_items MODIFY quantity INT NOT NULL DEFAULT 1");
        DB::statement("ALTER TABLE purchase_items MODIFY remaining_qty DECIMAL(12, 2) NOT NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE purchase_items MODIFY price DOUBLE(10, 2) NOT NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE purchase_items MODIFY purchase_price DOUBLE(10, 2) NOT NULL DEFAULT 0.00");
    }
};

