<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserBusiness;

class PlanService
{
    // ─── Get Subscription ────────────────────────────────────────────────────

    public function getSubscription(?int $businessId = null): ?Subscription
    {
        $businessId = $businessId ?? session('business_id');
        $business   = Business::find($businessId);

        if (!$business) {
            return null;
        }

        return $business->subscription;
    }

    public function hasSubscription(?int $businessId = null): bool
    {
        return $this->getSubscription($businessId) !== null;
    }

    // ─── Invoice Limits ──────────────────────────────────────────────────────

    public function canCreateInvoice(?int $businessId = null): bool
    {
        $businessId = $businessId ?? session('business_id');

        $sub = $this->getSubscription($businessId);

        if (!$sub) {
            return false;
        }

        if ($sub->plan->max_invoices === -1) {
            return true;
        }

        return $this->invoicesUsedCount($sub) < $sub->plan->max_invoices;
    }

    public function invoicesUsedCount(?Subscription $sub = null, ?int $businessId = null): int
    {
        $businessId = $businessId ?? session('business_id');
        $sub        ??= $this->getSubscription($businessId);

        if (!$sub) {
            return 0;
        }

        return Order::where('business_id', $businessId)
            ->where('created_at', '>=', $sub->countFrom())
            ->count();
    }

    public function invoicesRemaining(?int $businessId = null): ?int
    {
        $businessId = $businessId ?? session('business_id');

        $sub = $this->getSubscription($businessId);

        if (!$sub) {
            return 0;
        }

        if ($sub->plan->max_invoices === -1) {
            return null; // unlimited
        }

        return max(0, $sub->plan->max_invoices - $this->invoicesUsedCount($sub, $businessId));
    }

    // ─── User Limits ─────────────────────────────────────────────────────────

    public function canAddUser(?int $businessId = null): bool
    {
        $businessId = $businessId ?? session('business_id');
        $sub        = $this->getSubscription($businessId);

        if (!$sub) {
            return false;
        }

        if ($sub->plan->max_users === -1) {
            return true;
        }

        $used = UserBusiness::where('business_id', $businessId)->count();

        return $used < $sub->plan->max_users;
    }

    // ─── Feature Checks ──────────────────────────────────────────────────────

    public function hasFeature(string $feature, ?int $businessId = null): bool
    {
        $businessId = $businessId ?? session('business_id');

        $sub = $this->getSubscription($businessId);

        if (!$sub) {
            return false;
        }

        return $sub->plan->hasFeature($feature);
    }

    // ─── Full Usage Summary ──────────────────────────────────────────────────

    public function usageFor(?int $businessId = null): array
    {
        $businessId = $businessId ?? session('business_id');
        $sub        = $this->getSubscription($businessId);

        if (!$sub) {
            return [
                'plan'     => null,
                'period'   => null,
                'invoices' => [
                    'used'      => 0,
                    'limit'     => 0,
                    'remaining' => 0,
                    'unlimited' => false,
                    'breakdown' => ['pos' => 0, 'digital' => 0],
                ],
                'users'    => [
                    'used'      => UserBusiness::where('business_id', $businessId)->count(),
                    'limit'     => 0,
                    'remaining' => 0,
                    'unlimited' => false,
                ],
            ];
        }

        $plan      = $sub->plan;
        $countFrom = $sub->countFrom();

        $invoiceBase  = Order::where('business_id', $businessId)
            ->where('created_at', '>=', $countFrom);

        $invoicesUsed = (clone $invoiceBase)->count();
        $posUsed      = (clone $invoiceBase)->where('type', 'pos_invoice')->count();
        $digitalUsed  = (clone $invoiceBase)->where('type', '!=', 'pos_invoice')->count();
        $usersUsed    = UserBusiness::where('business_id', $businessId)->count();

        return [
            'plan'     => [
                'name'     => $plan->name,
                'slug'     => $plan->slug,
                'features' => $plan->features ?? [],
            ],
            'period'   => [
                'start'          => $sub->current_period_start,
                'end'            => $sub->current_period_end,
                'days_remaining' => $sub->daysRemaining(),
                'count_from'     => $countFrom,
            ],
            'invoices' => [
                'used'      => $invoicesUsed,
                'limit'     => $plan->max_invoices,
                'remaining' => $plan->max_invoices === -1 ? null : max(0, $plan->max_invoices - $invoicesUsed),
                'unlimited' => $plan->max_invoices === -1,
                'breakdown' => [
                    'pos'     => $posUsed,
                    'digital' => $digitalUsed,
                ],
            ],
            'users'    => [
                'used'      => $usersUsed,
                'limit'     => $plan->max_users,
                'remaining' => $plan->max_users === -1 ? null : max(0, $plan->max_users - $usersUsed),
                'unlimited' => $plan->max_users === -1,
            ],
        ];
    }

    // ─── Upgrade / Downgrade ─────────────────────────────────────────────────

    public function changePlan(Plan $newPlan, ?int $businessId = null): array
    {
        $businessId = $businessId ?? session('business_id');
        $sub = $this->getSubscription($businessId);

        if (!$sub) {
            return [
                'success' => false,
                'message' => 'No active subscription found. Please subscribe to a plan first.',
                'warning' => null,
            ];
        }

        $warning = null;
        if (
            $newPlan->max_invoices !== -1 &&
            $this->invoicesUsedCount($sub, $businessId) >= $newPlan->max_invoices
        ) {
            $warning = "You have already reached or exceeded the {$newPlan->name} plan invoice limit for this month. You will not be able to create invoices until next renewal.";
        }

        $sub->update([
            'plan_id'        => $newPlan->id,
            'usage_reset_at' => now(),
        ]);

        return [
            'success' => true,
            'warning' => $warning,
            'message' => "Plan changed to {$newPlan->name}. Your new limits are active immediately.",
        ];
    }
}