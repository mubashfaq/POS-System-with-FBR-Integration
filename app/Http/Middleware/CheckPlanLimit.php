<?php

namespace App\Http\Middleware;

use App\Services\PlanService;
use Closure;
use Illuminate\Http\Request;

class CheckPlanLimit
{
    public function __construct(private PlanService $planService) {}

    // Middleware - add the subscription check before the limit check
    public function handle(Request $request, Closure $next, string $resource): mixed
    {
        $businessId = session('business_id');

        // Check 1: Does the business have any plan at all?
        if (!$this->planService->hasSubscription($businessId)) {
            $message = 'You need an active subscription to access this feature.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success'     => false,
                    'message'     => $message,
                    'upgrade_url' => route('backend.admin.plans'),
                ], 403);
            }

            return redirect()->route('backend.admin.plans')->with('error', $message);
        }

        // Check 2: Have they hit their limit?
        $allowed = match ($resource) {
            'invoice' => $this->planService->canCreateInvoice($businessId),
            'user'    => $this->planService->canAddUser($businessId),
            default   => true,
        };

        if (!$allowed) {
            $messages = [
                'invoice' => 'You have reached your monthly invoice limit. Please upgrade your plan.',
                'user'    => 'You have reached your user limit. Please upgrade your plan.',
            ];

            $message = $messages[$resource] ?? 'You have reached your plan limit.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success'     => false,
                    'message'     => $message,
                    'upgrade_url' => route('backend.admin.plans'),
                ], 403);
            }

            return redirect()->back()->with('error', $message);
        }

        return $next($request);
    }
}
