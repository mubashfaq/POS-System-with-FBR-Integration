<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (!Schema::hasColumn('units', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('short_name');
            }
        });

        // Check duplicate titles (case-insensitive)
        $duplicates = DB::table('units')
            ->select(DB::raw('LOWER(title) as lower_title'), DB::raw('count(*) as count'))
            ->groupBy(DB::raw('LOWER(title)'))->having('count', '>', 1)->get();

        if ($duplicates->isEmpty()) {
            // Check if index doesn't already exist
            Schema::table('units', function (Blueprint $table) {
                $table->unique('title');
            });
        } else {
            Log::warning('Duplicate unit titles found, skipping unique index on units.title: ' . $duplicates->pluck('lower_title')->implode(', '));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            // Drop unique index if exists
            try {
                $table->dropUnique(['title']);
            } catch (\Throwable $e) {
                // Ignore if unique index did not exist
            }

            if (Schema::hasColumn('units', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};

