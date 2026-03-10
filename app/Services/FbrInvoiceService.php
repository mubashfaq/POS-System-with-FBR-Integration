<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Business;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FbrInvoiceService
{
    public function postInvoice($payload)
    {
        
        $token = FbrTokenService::getToken();

        $response = Http::withHeaders([
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json'
        ])->post("https://esp.fbr.gov.pk:8244/FBR/v1/api/Live/PostData", $payload);

        $json = $response->json();

        return [
            'success' => $json['Response'] === "Invoice received successfully",
            'invoice_no' => $json['InvoiceNumber'] ?? null,
            'raw' => $json
        ];
    }
}