<?php

namespace App\Http\Middleware;

use App\Services\PlanService;
use Closure;
use Illuminate\Http\Request;

class CheckPlanLimit
{
    public function __construct(private PlanService $planService) {}

    // Middleware - add the subscription check before the limit check
    public function handle(Request $request, Closure $next, string $resource, string $invoiceType = 'pos_invoice'): mixed
    {
        $businessId = session('business_id');

        // Check 1: Does the business have any plan at all?
        if (!$this->planService->hasSubscription($businessId)) {
            $message = 'You need an active subscription to access this feature.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success'     => false,
                    'message'     => $message,
                    'upgrade_url' => route('backend.admin.plans.choose'),
                ], 403);
            }

            return redirect()->route('backend.admin.plans.choose')->with('error', $message);
        }

        // Check 2: Have they hit their limit?
        $allowed = match ($resource) {
            'invoice' => $this->planService->canCreateInvoice($invoiceType ,$businessId),
            'user'    => $this->planService->canAddUser($businessId),
            default   => true,
        };

        if (!$allowed) {
            $businessType = $this->planService->businessType();
            $messages = [
                'invoice' => $this->invoiceLimitMessage($businessType, $invoiceType),
                'user'    => 'You have reached your user limit. Please upgrade your plan.',
            ];

            $message = $messages[$resource] ?? 'You have reached your plan limit.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success'     => false,
                    'message'     => $message,
                    'upgrade_url' => route('backend.admin.plans.choose'),
                ], 403);
            }

            return redirect()->back()->with('error', $message);
        }

        return $next($request);
    }

    private function invoiceLimitMessage(string $businessType, string $invoiceType): string
    {
        if ($businessType !== 'both') {
            return 'You have reached your monthly invoice limit. Please upgrade your plan to create more invoices.';
        }
 
        return match ($invoiceType) {
            'pos_invoice'     => 'You have reached your monthly POS invoice limit. Please upgrade your plan.',
            'digital_invoice' => 'You have reached your monthly Digital invoice limit. Please upgrade your plan.',
            default   => 'You have reached your invoice limit.',
        };
    }
}
