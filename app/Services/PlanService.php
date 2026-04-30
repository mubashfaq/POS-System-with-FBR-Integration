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

    public function businessType(): string
    {
        $businessId = session('business_id');

        return Business::where('id', $businessId)
            ->value('business_type') ?? 'both';
    }

    public function canCreateInvoice(string $invoiceType = 'pos_invoice', ?int $businessId = null): bool
    {
        $businessId   = $businessId ?? session('business_id');
        $sub          = $this->getSubscription($businessId);

        if (!$sub) {
            return false;
        }

        $plan         = $sub->plan;
        $businessType = $this->businessType();

        // pos or digital business → single shared pool, no per-type cap
        if ($businessType !== 'both') {
            $limit = $plan->combinedInvoiceLimit();
            if ($limit === -1) return true;

            $used = $this->totalInvoicesUsed($sub, $businessId);
            return $used < $limit;
        }

        // both business → each invoice type has its own separate cap
        return match ($invoiceType) {
            'pos_invoice' => $this->canCreatePosInvoice($sub, $plan, $businessId),
            default       => $this->canCreateDigitalInvoice($sub, $plan, $businessId),
        };
    }

    private function canCreatePosInvoice(Subscription $sub, Plan $plan, int $businessId): bool
    {
        if ($plan->max_pos_invoices === -1) return true;

        $used = Order::where('business_id', $businessId)
            ->where('type', 'pos_invoice')
            ->where('created_at', '>=', $sub->countFrom())
            ->count();

        return $used < $plan->max_pos_invoices;
    }

    private function canCreateDigitalInvoice(Subscription $sub, Plan $plan, int $businessId): bool
    {
        if ($plan->max_digital_invoices === -1) return true;

        $used = Order::where('business_id', $businessId)
            ->where('type', '!=', 'pos_invoice')
            ->where('created_at', '>=', $sub->countFrom())
            ->count();

        return $used < $plan->max_digital_invoices;
    }

    /**
     * Total invoices used regardless of type.
     * Used for pos/digital businesses (single shared pool).
     */
    public function totalInvoicesUsed(?Subscription $sub = null, ?int $businessId = null): int
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

    /**
     * POS invoices used this period (for 'both' businesses).
     */
    public function posInvoicesUsed(?Subscription $sub = null, ?int $businessId = null): int
    {
        $businessId = $businessId ?? session('business_id');
        $sub        ??= $this->getSubscription($businessId);

        if (!$sub) {
            return 0;
        }

        return Order::where('business_id', $businessId)
            ->where('type', 'pos_invoice')
            ->where('created_at', '>=', $sub->countFrom())
            ->count();
    }

    /**
     * Digital invoices used this period (for 'both' businesses).
     */
    public function digitalInvoicesUsed(?Subscription $sub = null, ?int $businessId = null): int
    {
        $businessId = $businessId ?? session('business_id');
        $sub        ??= $this->getSubscription($businessId);

        if (!$sub) {
            return 0;
        }

        return Order::where('business_id', $businessId)
            ->where('type', '!=', 'pos_invoice')
            ->where('created_at', '>=', $sub->countFrom())
            ->count();
    }


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


    public function hasFeature(string $feature, ?int $businessId = null): bool
    {
        $businessId = $businessId ?? session('business_id');

        $sub = $this->getSubscription($businessId);

        if (!$sub) {
            return false;
        }

        return $sub->plan->hasFeature($feature);
    }

    public function usageFor(?int $businessId = null): array
    {
        $businessId   = $businessId ?? session('business_id');
        $sub          = $this->getSubscription($businessId);

        if (!$sub) {
            return [
                'plan'          => null,
                'period'        => null,
                'business_type' => $this->businessType(),
                'invoices'      => [
                    'mode'      => 'shared',
                    'used'      => 0,
                    'limit'     => 0,
                    'remaining' => 0,
                    'unlimited' => false,
                    'breakdown' => ['pos' => 0, 'digital' => 0],
                ],
                'users' => [
                    'used'      => UserBusiness::where('business_id', $businessId)->count(),
                    'limit'     => 0,
                    'remaining' => 0,
                    'unlimited' => false,
                ],
            ];
        }

        $plan         = $sub->plan;
        $businessType = $this->businessType();
        $countFrom    = $sub->countFrom();
        $usersUsed    = UserBusiness::where('business_id', $businessId)->count();

        // ── Invoice usage 
        if ($businessType !== 'both') {
            // Single shared pool — all invoices count together
            $totalUsed     = $this->totalInvoicesUsed($sub, $businessId);
            $combinedLimit = $plan->combinedInvoiceLimit();

            $invoices = [
                'mode'      => 'shared',
                'used'      => $totalUsed,
                'limit'     => $combinedLimit,
                'remaining' => $combinedLimit === -1 ? null : max(0, $combinedLimit - $totalUsed),
                'unlimited' => $combinedLimit === -1,
            ];
        } else {
            // Separate buckets — POS and Digital tracked independently
            $posUsed     = $this->posInvoicesUsed($sub, $businessId);
            $digitalUsed = $this->digitalInvoicesUsed($sub, $businessId);

            $invoices = [
                'mode'    => 'split',
                'pos'     => [
                    'used'      => $posUsed,
                    'limit'     => $plan->max_pos_invoices,
                    'remaining' => $plan->max_pos_invoices === -1 ? null : max(0, $plan->max_pos_invoices - $posUsed),
                    'unlimited' => $plan->max_pos_invoices === -1,
                ],
                'digital' => [
                    'used'      => $digitalUsed,
                    'limit'     => $plan->max_digital_invoices,
                    'remaining' => $plan->max_digital_invoices === -1 ? null : max(0, $plan->max_digital_invoices - $digitalUsed),
                    'unlimited' => $plan->max_digital_invoices === -1,
                ],
                'total_used'  => $posUsed + $digitalUsed,
                'total_limit' => $plan->combinedInvoiceLimit(),
            ];
        }

        return [
            'plan' => [
                'name'     => $plan->name,
                'slug'     => $plan->slug,
                'features' => $plan->features ?? [],
            ],
            'period' => [
                'start'          => $sub->current_period_start,
                'end'            => $sub->current_period_end,
                'days_remaining' => $sub->daysRemaining(),
                'count_from'     => $countFrom,
            ],
            'business_type' => $businessType,
            'invoices'      => $invoices,
            'users' => [
                'used'      => $usersUsed,
                'limit'     => $plan->max_users,
                'remaining' => $plan->max_users === -1 ? null : max(0, $plan->max_users - $usersUsed),
                'unlimited' => $plan->max_users === -1,
            ],
        ];
    }


    public function changePlan(Plan $newPlan, ?int $businessId = null): array
    {
        $businessId   = $businessId ?? session('business_id');
        $sub          = $this->getSubscription($businessId);

        if (!$sub) {
            return [
                'success' => false,
                'message' => 'No active subscription found. Please subscribe to a plan first.',
                'warning' => null,
            ];
        }

        $businessType = $this->businessType();
        $warning      = null;

        if ($businessType !== 'both') {
            $newLimit = $newPlan->combinedInvoiceLimit();
            if ($newLimit !== -1 && $this->totalInvoicesUsed($sub, $businessId) >= $newLimit) {
                $warning = "You have already reached or exceeded the {$newPlan->name} plan invoice limit. You will not be able to create invoices until next renewal.";
            }
        } else {
            $posUsed     = $this->posInvoicesUsed($sub, $businessId);
            $digitalUsed = $this->digitalInvoicesUsed($sub, $businessId);
            $warnings    = [];

            if ($newPlan->max_pos_invoices !== -1 && $posUsed >= $newPlan->max_pos_invoices) {
                $warnings[] = "POS invoice limit ({$newPlan->max_pos_invoices})";
            }
            if ($newPlan->max_digital_invoices !== -1 && $digitalUsed >= $newPlan->max_digital_invoices) {
                $warnings[] = "Digital invoice limit ({$newPlan->max_digital_invoices})";
            }

            if (!empty($warnings)) {
                $warning = "You have already reached: " . implode(' and ', $warnings) . " on the new plan. Those invoice types will be blocked until next renewal.";
            }
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