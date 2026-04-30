<?php

namespace App\Http\Middleware;

use App\Services\PlanService;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware to restrict routes to plans that have a specific feature.
 *
 * Usage in routes:
 *   Route::get('/reports', ...)->middleware('feature:reports');
 *   Route::get('/export', ...)->middleware('feature:exports');
 *   Route::get('/barcode', ...)->middleware('feature:barcode');
 */
class RequireFeature
{
    public function __construct(private PlanService $planService) {}

    public function handle(Request $request, Closure $next, string $feature): mixed
    {
        $businessId = session('business_id');

        if (!$this->planService->hasFeature($feature, $businessId)) {
            $planName = $this->planService->getSubscription($businessId)->plan->name;

            $message = "The '{$feature}' feature is not available on the {$planName} plan. Please upgrade to access this feature.";

            if ($request->expectsJson()) {
                return response()->json([
                    'success'     => false,
                    'message'     => $message,
                    'upgrade_url' => route('backend.admin.plans.choose'),
                ], 403);
            }

            return redirect()->route('backend.admin.plans.choose')->with('error', $message);
        }

        return $next($request);
    }
}