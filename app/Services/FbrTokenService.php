<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Business;

class FbrTokenService
{
    public static function getSettings()
    {
        $businessId = session('business_id');

        return DB::table('fbr_tokens')
            ->where('business_id', $businessId)
            ->latest('id')
            ->first();
    }

    public static function isIntegrationEnabled()
    {
        $settings = self::getSettings();

        if (!$settings) {
            return false;
        }

        return $settings->fbr_integration === 'enabled';
    }

    public static function getToken()
    {
        $settings = self::getSettings();

        if (!$settings) {
            return null;
        }

        $businessId = session('business_id');
        $business = Business::find($businessId);

        if (!$business) {
            return null;
        }

        $type = $business->business_type;

        // 1 = production, 0 = sandbox
        if ($settings->environment == 1) {
            return $type === 'digital'
                ? $settings->digital_production_token
                : $settings->pos_production_token;
        }

        return $type === 'digital'
            ? $settings->digital_sandbox_token
            : $settings->pos_sandbox_token;
    }
}