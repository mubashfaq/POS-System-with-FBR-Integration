<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    // Return all businesses of logged-in user
    public function index(Request $request)
    {
        $businesses = $request->user()
            ->businesses()
            ->get();

        return response()->json([
            'status' => true,
            'businesses' => $businesses
        ]);
    }

    // Select business
    public function select(Request $request)
    {
        $request->validate([
            'business_id' => 'required|exists:businesses,id'
        ]);

        $user = $request->user();

        $business = $user->businesses()
            ->where('businesses.id', $request->business_id)
            ->first();

        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized business'
            ], 403);
        }

        return response()->json([
            'status' => true,
            'message' => 'Business selected successfully',
            'business' => $business
        ]);
    }

}