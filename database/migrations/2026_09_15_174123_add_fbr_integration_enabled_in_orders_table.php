<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('fbr_integration_enabled')->default(false)->after('invoice_environment');
        });

        // If an order already has an FBR response, FBR must have been enabled at the time
        DB::table('orders')
            ->whereNotNull('api_status')
            ->update(['fbr_integration_enabled' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
};
