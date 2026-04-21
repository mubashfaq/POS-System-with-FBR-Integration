<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PlanService;

class PlanController extends Controller
{
    public function __construct(private PlanService $planService) {}

    public function index()
    {
        $plans       = Plan::active()->get();
        $user        = auth()->user();
        $currentPlan = null;
        $usage       = null;

        if ($user) {
            try {
                $sub         = $this->planService->getSubscription();
                $currentPlan = $sub->plan;
                $usage       = $this->planService->usageFor();
            } catch (\Exception $e) {
                // No subscription yet — show plans without highlighting
            }
        }
        return view('backend.plans.index', compact('plans', 'currentPlan', 'usage'));
    }

    public function indexFront()
    {
        $plans       = Plan::active()->get();
        $user        = auth()->user();
        $currentPlan = null;
        $usage       = null;

        if ($user) {
            try {
                $sub         = $this->planService->getSubscription();
                $currentPlan = $sub->plan;
                $usage       = $this->planService->usageFor();
            } catch (\Exception $e) {
                // No subscription yet — show plans without highlighting
            }
        }

        return view('frontend.plans.index', compact('plans', 'currentPlan', 'usage'));
    }
}
