<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (!Auth::validate($request->only('email', 'password'))) {
            return response()->json([
                'status' => false,
                'message' => 'Incorrect email or password'
            ], 401);
        }

        $user = User::where('email', $request->email)->first();

        if ($user->is_suspended == 1) {
            return response()->json([
                'status' => false,
                'message' => 'Your account is temporarily suspended'
            ], 403);
        }

        // Delete old tokens (optional)
        $user->tokens()->delete();

        $token = $user->createToken('mobile_token')->plainTextToken;

        // Load user businesses
        $businesses = $user->businesses()->get();

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $token,
            'businesses' => $businesses
        ]);
    }

    // public function show(Request $request)
    // {
    //     return response()->json([
    //         'status' => true,
    //         'user' => $request->user()
    //     ]);
    // }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    
}