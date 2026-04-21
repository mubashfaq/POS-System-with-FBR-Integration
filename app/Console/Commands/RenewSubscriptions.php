<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RenewSubscriptions extends Command
{
    protected $signature   = 'subscriptions:renew';
    protected $description = 'Renew subscriptions whose billing period has ended and reset monthly usage.';

    public function handle(): void
    {
        $due = Subscription::dueForRenewal()->with(['business', 'plan'])->get();

        if ($due->isEmpty()) {
            $this->info('No subscriptions due for renewal.');
            return;
        }

        $now = now();

        foreach ($due as $subscription) {
            try {
                $subscription->update([
                    'current_period_start' => $now,
                    'current_period_end'   => $now->copy()->addMonth(),
                    'usage_reset_at'       => $now,  // fresh month = fresh invoice count
                ]);

                // Optionally fire an event here:
                // event(new SubscriptionRenewed($subscription));

                $this->line("✓ Renewed: Business #{$subscription->business_id} — Plan: {$subscription->plan->name}");

                Log::info('Subscription renewed', [
                    'business_id' => $subscription->business_id,
                    'plan'        => $subscription->plan->name,
                    'period'      => "{$now} → {$now->copy()->addMonth()}",
                ]);

            } catch (\Exception $e) {
                $this->error("✗ Failed to renew subscription #{$subscription->id}: {$e->getMessage()}");
                Log::error('Subscription renewal failed', [
                    'subscription_id' => $subscription->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Renewed {$due->count()} subscription(s).");
    }
}