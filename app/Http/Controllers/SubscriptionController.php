<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PlanService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private PlanService $planService) {}


    public function index()
    {
        $businessId = session('business_id');
        $subscription = Subscription::where('business_id', $businessId)->first();

        try {
            $usage = $this->planService->usageFor($businessId);
        } catch (\Exception $e) {
            return redirect()->route('backend.admin.plans')
                ->with('info', 'Please select a plan to get started.');
        }
        return view('backend.subscriptions.index', compact('usage', 'subscription'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403);
        };
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);
        $now  = now();
        $plan = Plan::findOrFail($request->plan_id);

        Subscription::create([
            'business_id'          => session('business_id'),
            'plan_id'              => $plan->id,
            'current_period_start' => $now,
            'current_period_end'   => $now->copy()->addMonth(),
            'usage_reset_at'       => $now,
            'starts_at'            => $now,
            'ends_at'              => null,
            'status'               => 'active',
        ]);

        return redirect()->route('backend.admin.subscription.index')
            ->with('success', "Welcome to the {$plan->name} plan! Your subscription is now active.");
    }

    public function changePlan(Request $request)
    {
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403);
        };
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $businessId = session('business_id');
        $newPlan = Plan::findOrFail($request->plan_id);

        $currentPlanId = $this->planService->getSubscription($businessId)->plan_id;
        if ($currentPlanId === $newPlan->id) {
            return back()->with('info', 'You are already on this plan.');
        }

        $result = $this->planService->changePlan($newPlan, $businessId);

        return redirect()->route('backend.admin.subscription.index')
            ->with('success', $result['message'])
            ->with('warning', $result['warning']);
    }

    public function cancel()
    {
        $business = auth()->user()->businesses()->where('businesses.id', session('business_id'))->firstOrFail();

        $business->subscription()->update(['status' => 'cancelled']);

        return redirect()->route('backend.admin.subscription.cancelled');
    }

    public function updateStatus(Request $request)
    {
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403);
        };
        $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
            'status' => 'required|in:active,cancelled,expired',
        ]);

        $subscription = Subscription::findOrFail($request->subscription_id);

        $subscription->update([
            'status' => $request->status
        ]);

            // 🎯 Redirect based on status
        if ($request->status === 'cancelled') {
            return redirect()->route('backend.admin.subscription.cancelled')
                ->with('success', 'Subscription cancelled');
        }

        if ($request->status === 'expired') {
            return redirect()->route('backend.admin.subscription.expired')
                ->with('success', 'Subscription expired');
        }

        return back()->with('success', 'Subscription activated');
    }

    public function cancelled()
    {
        return view('backend.subscriptions.cancelled');
    }

    public function expired()
    {
        return view('backend.subscriptions.expired');
    }
}
