<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Business;


class FbrTokenService
{
    public static function getToken()
    {
        $businessId = session('business_id');

        // Get business type from businesses table
        $business = Business::findOrFail($businessId);

        if (!$business) {
            return null;
        }

        $type = $business->business_type;

        $record = DB::table('fbr_tokens')
            ->where('business_id', $businessId)
            ->latest('id')
            ->first();

        if (!$record) {
            return null;
        }

        // 1 = production, 0 = sandbox
        if ($record->environment == 1) {
            return $type === 'digital'
                ? $record->digital_production_token
                : $record->pos_production_token;
        }

        return $type === 'digital'
            ? $record->digital_sandbox_token
            : $record->pos_sandbox_token;
    }
}