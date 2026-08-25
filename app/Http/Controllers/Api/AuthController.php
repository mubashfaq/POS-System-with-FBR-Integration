<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\ForgetPassword;
use App\Mail\PasswordReset;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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

        if ($user->is_mobile == 0) {
            return response()->json([
                'status' => false,
                'message' => 'Your account are not allowed to login from mobile'
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

    public function forgetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $findUser = User::where('email', $request->email)->first();

        if (!$findUser) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($findUser->is_suspended == 1) {
            return response()->json([
                'status' => false,
                'message' => 'Your account is temporarily suspended'
            ], 403);
        }

        if ($findUser->is_mobile == 0) {
            return response()->json([
                'status' => false,
                'message' => 'Your account is not allowed to use the mobile app'
            ], 403);
        }

        $otp = rand(11111, 99999);

        ForgetPassword::updateOrCreate(
            ['user_id' => $findUser->id],
            [
                'email' => $findUser->email,
                'otp' => $otp,
                'reset_token' => null, // clear any old reset token
                'suspend_duration' => now()->addMinutes(5),
                'resent_count' => 0,
            ]
        );

        $mailData = [
            'title' => readConfig('site_name'),
            'otp' => $otp,
            'name' => $findUser->name,
        ];

        Mail::to($findUser->email)->send(new PasswordReset($mailData));

        return response()->json([
            'status' => true,
            'message' => 'Check your inbox for OTP code',
        ]);
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $record = ForgetPassword::where('email', $request->email)->first();

        if (!$record) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ], 404);
        }

        $user = User::where('email', $request->email)->first();
        $otp = rand(11111, 99999);

        $record->otp = $otp;
        $record->resent_count++;
        $record->reset_token = null;
        $record->suspend_duration = now()->addMinutes(5);
        $record->save();

        $mailData = [
            'title' => readConfig('site_name'),
            'otp' => $otp,
            'name' => $user->name,
        ];

        Mail::to($record->email)->send(new PasswordReset($mailData));

        return response()->json([
            'status' => true,
            'message' => 'OTP resent successfully',
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required',
        ]);

        $record = ForgetPassword::where('email', $request->email)
            ->where('otp', $request->otp)
            ->first();

        if (!$record) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP'
            ], 422);
        }

        if (now()->greaterThan($record->suspend_duration)) {
            return response()->json([
                'status' => false,
                'message' => 'OTP expired'
            ], 422);
        }

        // Issue a one-time reset token instead of relying on session
        $resetToken = Str::random(60);
        $record->reset_token = $resetToken;
        $record->otp = null; // OTP is now consumed, can't be reused
        $record->save();

        return response()->json([
            'status' => true,
            'message' => 'OTP verified',
            'reset_token' => $resetToken,
        ]);
    }

    public function newPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'reset_token' => 'required',
            'password' => 'required|confirmed|min:6',
        ]);

        $record = ForgetPassword::where('email', $request->email)
            ->where('reset_token', $request->reset_token)
            ->first();

        if (!$record) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired reset request'
            ], 422);
        }

        if (now()->greaterThan($record->suspend_duration)) {
            $record->delete();
            return response()->json([
                'status' => false,
                'message' => 'Reset session expired, please request a new OTP'
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        $user->password = bcrypt($request->password);
        $user->save();

        // Clean up: invalidate all tokens so old sessions/devices are logged out
        $user->tokens()->delete();

        $record->delete();

        return response()->json([
            'status' => true,
            'message' => 'Password reset successfully',
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