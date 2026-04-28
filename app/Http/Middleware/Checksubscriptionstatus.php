<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
/**
 * Middleware to block access if the business has no active subscription.
 *
 * Exempt routes (always allowed through):
 *   - /subscription/* (manage subscription)
 *   - /plans          (view/choose a plan)
 *   - /logout
 */
class CheckSubscriptionStatus
{
    // Routes that are always accessible regardless of subscription status
    private array $exempt = [
        // 'backend.admin.subscription.*',
        'backend.admin.plans',
        'logout',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        // Skip for guests
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // ✅ SUPER ADMIN → skip subscription check
        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        // Skip exempt routes
        foreach ($this->exempt as $pattern) {
            if ($request->routeIs($pattern)) {
                return $next($request);
            }
        }

        $businessId = session('business_id');

        if (!$businessId) {
            return $next($request);
        }

        $business = $user->businesses()
            ->where('businesses.id', $businessId)
            ->first();

        if (!$business) {
            return $next($request);
        }

        $subscription = $business->subscription;

        // No subscription
        if (!$subscription) {
            return redirect()->route('backend.admin.plans')
                ->with('warning', 'Please activate a plan to continue.');
        }

        // Cancelled
        if ($subscription->status === 'cancelled') {
            return redirect()->route('backend.admin.subscription.cancelled');
        }

        // Expired
        if ($subscription->status === 'expired') {
            return redirect()->route('backend.admin.subscription.expired');
        }

        return $next($request);
    }
}
