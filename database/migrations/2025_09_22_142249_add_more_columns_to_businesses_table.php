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
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('email')->nullable()->after('business_name');
            $table->string('phone')->nullable()->after('email');
            $table->string('logo')->nullable()->after('phone');
            $table->string('currency', 10)->nullable()->after('logo');
            $table->string('facebook_link')->nullable()->after('currency');
            $table->string('whatsapp_link')->nullable()->after('facebook_link');
            $table->string('youtube_link')->nullable()->after('whatsapp_link');
            $table->string('instagram_link')->nullable()->after('youtube_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'phone',
                'logo',
                'currency',
                'facebook_link',
                'whatsapp_link',
                'youtube_link',
                'instagram_link',
            ]);
        });
    }
};
