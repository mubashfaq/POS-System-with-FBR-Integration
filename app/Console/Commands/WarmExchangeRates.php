<?php
namespace App\Console\Commands;

use App\Models\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Log;

class WarmExchangeRates extends Command
{
    protected $signature = 'exchange:warm';
    protected $description = 'Pre-fetch exchange rates for all active currencies';

    public function handle()
    {
        $currencies = Currency::where('code', '!=', 'PKR')->pluck('code');

        foreach ($currencies as $code) {
            $code = strtolower($code);
            $cacheKey = "exchange_rate_{$code}_pkr";

            try {
                $url = "https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/{$code}.json";
                $response = Http::timeout(8)->get($url);

                if (!$response->successful()) {
                    $this->warn("Failed: {$code}");
                    continue;
                }

                $rate = $response->json()[$code]['pkr'] ?? null;

                if ($rate && $rate > 0) {
                    Cache::put($cacheKey, round((float) $rate, 2), now()->addHours(6));
                    Log::channel('schedule')->info("Cached: {$code} = " . round((float) $rate, 2));
                    $this->info("Cached: {$code} = " . round((float) $rate, 2));
                }
            } catch (\Throwable $e) {
                Log::channel('schedule')->error("Error {$code}: " . $e->getMessage());
                $this->error("Error {$code}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}