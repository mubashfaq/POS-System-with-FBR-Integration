<?php

namespace App\Console;

use App\Jobs\SendFBRInvoiceJob;
use App\Models\Order;
use App\Models\OrderLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */

    protected $commands = [
        \App\Console\Commands\RenewSubscriptions::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('subscriptions:renew')->dailyAt('00:05')->withoutOverlapping();
        $schedule->command('exchange:warm')->everyFourHours()->withoutOverlapping();
        $schedule->call(function () {
            // Log scheduler start
            Log::channel('schedule')->info('SCHEDULER RUNNING');

            // Fetch failed orders (limit 50)
            $failedOrders = Order::where(function ($q) {
                $q->whereNull('api_status')
                    ->orWhere('api_status', '!=', 200);
            })->limit(50)->get();

            foreach ($failedOrders as $order) {
                try {
                    // Load relations
                    $order->load(['products.product', 'customer', 'business']);

                    if (!$order) {
                        throw new \Exception('Order not found');
                    }

                    if (!$order->business || !$order->customer) {
                        throw new \Exception('Business or Customer missing for Order ID ' . $order->id);
                    }

                    // ===============================
                    // BUILD FBR ITEMS
                    // ===============================
                    $items = [];
                    foreach ($order->products as $orderProduct) {
                        if (!$orderProduct->product) {
                            throw new \Exception('Product missing for order product ID: ' . $orderProduct->id);
                        }

                        $product = $orderProduct->product;
                        $quantity = round($orderProduct->quantity, 4);
                        $lineTotal = round($orderProduct->total, 2);

                        $rateText = trim($product->rate_of_tax);

                        if (str_ends_with($rateText, '%')) {
                            $rate = (float) str_replace('%', '', $rateText);
                            $valueExTax = round($lineTotal / (1 + ($rate / 100)), 2);
                            $salesTax = round($lineTotal - $valueExTax, 2);
                        } else {
                            $salesTax = 0;
                            $valueExTax = $lineTotal;
                        }

                        $items[] = [
                            "hsCode" => $product->hsCode,
                            "productDescription" => $product->hs_description,
                            "rate" => $rateText,
                            "uoM" => $product->uom,
                            "quantity" => (float) number_format($quantity, 4, '.', ''),
                            "totalValues" => (float) number_format($lineTotal, 2, '.', ''),
                            "valueSalesExcludingST" => (float) number_format($valueExTax, 2, '.', ''),
                            "salesTaxApplicable" => (float) number_format($salesTax, 2, '.', ''),
                            "saleType" => $product->sale_type,
                            "sroScheduleNo" => $product->sro_schedule,
                            "sroItemSerialNo" => $product->sro_item,
                        ];
                    }

                    // ===============================
                    // BUILD PAYLOAD
                    // ===============================
                    $payload = [
                        "invoiceType" => "Sale Invoice",
                        "invoiceDate" => now()->format('Y-m-d'),
                        "sellerNTNCNIC" => $order->business->cnic,
                        "sellerBusinessName" => $order->business->business_name,
                        "sellerProvince" => $order->business->state,
                        "sellerAddress" => $order->business->address,
                        "buyerBusinessName" => $order->customer->name,
                        "buyerProvince" => $order->customer->province ?? "Punjab",
                        "buyerAddress" => $order->customer->address ?? "",
                        "buyerRegistrationType" => $order->customer->is_registered
                            ? "Registered"
                            : "Unregistered",
                        "invoiceRefNo" => "INV-" . $order->id,
                        "scenarioId" => "SN002",
                        "items" => $items
                    ];

                    // ===============================
                    // SEND TO FBR
                    // ===============================
                    $response = Http::withToken(config('services.fbr.token'))
                        ->timeout(60)
                        ->post(
                            'https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata_sb',
                            $payload
                        );

                    $json = $response->json();

                    $errorCode = data_get($json, 'validationResponse.invoiceStatuses.0.errorCode')
                        ?? data_get($json, 'errorCode');

                    $errorMessage = data_get($json, 'validationResponse.invoiceStatuses.0.error')
                        ?? data_get($json, 'error');

                    // ===============================
                    // UPDATE ORDER
                    // ===============================
                    $order->update([
                        'api_status' => $response->status(),
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage,
                        'api_message' => $response->ok(),
                    ]);

                    // ===============================
                    // LOG RESPONSE
                    // ===============================
                    OrderLog::create([
                        'business_id' => session('business_id'),
                        'user_id' => auth()->id(),
                        'order_id' => $order->id,
                        'request_payload' => $payload,
                        'response' => $json,
                        'status_code' => $response->status(),
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage,
                        'success' => $response->ok(),
                    ]);

                    Log::channel('schedule')->info('FBR ORDER COMPLETED', [
                        'order_id' => $order->id,
                        'status' => $response->status(),
                    ]);

                } catch (\Throwable $e) {
                    Log::channel('schedule')->error('FBR ORDER FAILED', [
                        'order_id' => $order->id,
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }
        })->everyFourHours();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
