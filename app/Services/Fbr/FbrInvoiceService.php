<?php
// app/Services/Fbr/FbrInvoiceService.php
namespace App\Services\Fbr;

use App\Exceptions\FbrRejectedException;
use App\Models\{Business, Customer, Order};
use Illuminate\Support\Facades\{Http, Log};

class FbrInvoiceService
{
    public function buildPayload(Order $order, Customer $customer, array $lines): array
    {
        $items = array_map(fn($l) => [
            'ItemCode' => $l['product']->sku,
            'ItemName' => $l['product']->name,
            'quantity' => round($l['qty'], 2),
            'PCTCode' => $l['product']->PCTCode,
            'TaxRate' => $l['calc']['tax_rate'],
            'SaleValue' => $l['calc']['line_total'],
            'TotalAmount' => round($l['calc']['line_total'] + $l['calc']['sale_tax'], 2),
            'TaxCharged' => $l['calc']['sale_tax'],
            'discount' => 0,
            'InvoiceType' => 1,
        ], $lines);

        return [
            'POSID' => $order->POSID,
            'USIN' => 'USIN-' . $order->business_id . '-' . str_pad($order->invoice_number, 4, '0', STR_PAD_LEFT),
            'DateTime' => \Carbon\Carbon::parse($order->order_date)->format('Y-m-d'),
            'buyerNTN' => $customer->ntn_cnic,
            'buyerCNIC' => $customer->ntn_cnic,
            'BuyerName' => $customer->name,
            'BuyerPhoneNumber' => $customer->phone,
            'items' => $items,
            // FIX: totals across ALL items (the web version only used the last item)
            'TotalBillAmount' => round(array_sum(array_column($items, 'TotalAmount')), 2),
            'TotalQuantity' => round(array_sum(array_column($items, 'quantity')), 2),
            'TotalSaleValue' => round(array_sum(array_column($items, 'SaleValue')), 2),
            'TotalTaxCharged' => round(array_sum(array_column($items, 'TaxCharged')), 2),
            'PaymentMode' => 2,
            'InvoiceType' => 1,
        ];
    }

    /** @throws FbrRejectedException */
    public function send(Business $business, array $payload, string $env, string $token): array
    {
        $url = $business->business_type === 'pral'
            ? "https://ims.pral.com.pk/ims/{$env}/api/Live/PostData"
            : "https://gw.fbr.gov.pk/imsp/v1/api/Live/PostData";

            // production  https://gw.fbr.gov.pk/imsp/v1/api/Live/PostData
            // sandbox  https://esp.fbr.gov.pk:8244/FBR/v1/api/Live/PostData

        try {
            $response = Http::withOptions([
                'timeout' => 120,
                'connect_timeout' => 30,
                'verify' => false, // ideally true in production; see notes
            ])
                ->withToken($token)
                ->acceptJson()
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::channel('fbr')->error('FBR request failed', ['error' => $e->getMessage(), 'payload' => $payload]);
            
            throw new FbrRejectedException('FBR request failed: ' . $e->getMessage(), null, [], $payload);
        }

        $json = $response->json() ?? [];

        Log::channel('fbr')->info('FBR response', [
            'payload' => $payload,
            'response' => $json,
            'http_status' => $response->status(),
        ]);

        if (($json['Response'] ?? null) !== 'Invoice received successfully') {
            $errorCode = $json['Code']
                ?? $json['fault']['code']
                ?? null;

            $errorMessage = $json['Response']
                ?? $json['fault']['message']
                ?? $json['fault']['description']
                ?? 'Unexpected FBR response';

            throw new FbrRejectedException(
                $errorMessage,
                $errorCode !== null ? (string) $errorCode : null,
                $json,
                $payload
            );
        }

        return ['invoice_number' => $json['InvoiceNumber'] ?? null, 'http_status' => $response->status(), 'raw' => $json];
    }
}