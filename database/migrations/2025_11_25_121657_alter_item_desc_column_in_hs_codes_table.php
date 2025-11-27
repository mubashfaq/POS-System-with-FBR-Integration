<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hs_codes', function (Blueprint $table) {
            $table->longText('item_desc')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hs_codes', function (Blueprint $table) {
            $table->string('item_desc', 255)->change();
        });
    }
};
