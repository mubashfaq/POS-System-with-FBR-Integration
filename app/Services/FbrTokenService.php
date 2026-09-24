<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Business;

class FbrTokenService
{
    public static function getSettings($businessId = null)
    {
        $businessId = $businessId ?? session('business_id');

        return DB::table('fbr_tokens')
            ->where('business_id', $businessId)
            ->latest('id')
            ->first();
    }

    public static function isIntegrationEnabled($businessId = null)
    {
        $settings = self::getSettings($businessId);

        if (!$settings) {
            return false;
        }

        return $settings->fbr_integration === 'enabled';
    }

    public static function getToken($businessId = null)
    {
        $settings = self::getSettings($businessId);

        // if (!$settings || $settings->fbr_integration !== 'enabled') {
        //     return null;
        // }

        $businessId = $businessId ?? session('business_id');
        $business = Business::find($businessId);

        if (!$business) {
            return null;
        }

        $type = $business->business_type;

        // 1 = production, 0 = sandbox
        $env = $settings->environment == 1 ? 'production' : 'sandbox';

        switch ($type) {
            case 'digital':
                return $settings->{"digital_{$env}_token"};

            case 'pos':
                return $settings->{"pos_{$env}_token"};

            case 'pral':
                return $settings->{"pral_{$env}_token"};

            default:
                return [
                    'digital_token' => $settings->{"digital_{$env}_token"},
                    'pos_token' => $settings->{"pos_{$env}_token"},
                ];
        }
    }

    public static function getPOSID($businessId = null)
    {
        $settings = self::getSettings($businessId);

        return $settings->environment == 1 ? $settings->POSID_production : $settings->POSID_sandbox;
    }

    public static function getURLEnvironment($businessId = null)
    {
        $settings = self::getSettings($businessId);

        // if (!$settings || $settings->fbr_integration !== 'enabled') {
        //     return null;
        // }

        return $settings->environment == 1 ? 'production' : 'sandbox';
    }

    public static function getTokenURL($businessId = null)
    {
        $settings = self::getSettings($businessId);

        if (!$settings || $settings->fbr_integration !== 'enabled') {
            return null;
        }

        return $settings->environment == 1? '' : '_sb';


    }
}