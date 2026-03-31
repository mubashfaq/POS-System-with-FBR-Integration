<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PosCart;
use App\Services\FbrInvoiceService;
use App\Services\FbrTokenService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => ['required', 'exists:customers,id', 'integer'],
            'order_discount' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'check_duplicate' => ['nullable', 'boolean']
        ]);

        $business = $request->attributes->get('business');
        $businessId = $business->id;
        $customerId = $request->customer_id;
        $userId = auth()->id();
        $checkDuplicate = $request->check_duplicate ?? false;
        $paid = $request->paid ?? 0;
        $orderDiscount = $request->order_discount ?? 0;

        try {

            $customer = Customer::findOrFail($customerId);

            $carts = PosCart::with(['product', 'purchaseItem'])
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->get();

            if ($carts->isEmpty()) {
                return response()->json(['error' => 'Cart is empty'], 422);
            }

            /* ================= DUPLICATE CHECK ================= */
            if ($checkDuplicate) {

                $cartProductIds = $carts->pluck('product.id')->toArray();

                $duplicateOrder = Order::where('customer_id', $customerId)
                    ->where('business_id', $businessId)
                    ->whereHas('products', function ($q) use ($cartProductIds) {
                        $q->whereIn('product_id', $cartProductIds);
                    })
                    ->first();

                if ($duplicateOrder) {
                    return response()->json([
                        'duplicate' => true,
                        'message'   => 'Same order already exists'
                    ]);
                }

                return response()->json(['duplicate' => false]);
            }

            DB::beginTransaction();

            $order = Order::create([
                'customer_id' => $customerId,
                'user_id' => $userId,
                'business_id' => $businessId,
            ]);


            $totalAmountOrder = 0;
            $fbrItems = [];

            foreach ($carts as $cart) {

                $product = $cart->product;

                $unitPrice = $cart->price_override ?? $product->discounted_price;
                $qty = $cart->quantity;
                $lineTotal = round($unitPrice * $qty, 2);

                /* ================= FURTHER TAX ================= */
                $rateText = trim($product->pos_sale_tax);
                $taxRate = (float) str_replace('%', '', $rateText);

                if (str_ends_with($rateText, '%')) {
                    $valueExTax = round($lineTotal / (1 + ($taxRate / 100)), 2);
                    $salesTax  = round($valueExTax * ($taxRate / 100), 2);
                } else {
                    $salesTax = floatval(preg_replace('/[^0-9.]/', '', $rateText));
                    $valueExTax = round($lineTotal - $salesTax, 2);
                }

                $furtherTax = 0;

                if (
                    strtolower($customer->is_registered) === 'unregistered' &&
                    $product->sale_type != '3rd Schedule Goods'
                ) {
                    $furtherTax = round($valueExTax * 0.04, 2);
                }

                $finalTotal = $lineTotal + $furtherTax;

                $order->products()->create([
                    'quantity' => $qty,
                    'price' => $unitPrice,
                    'purchase_price' => $cart->purchaseItem ? $cart->purchaseItem->purchase_price : $product->purchase_price,
                    'sub_total' => $valueExTax,
                    'total' => $finalTotal,
                    'product_id' => $product->id,
                    'purchase_item_id' => $cart->purchase_item_id,
                ]);

                $product->quantity -= $qty;
                $product->save();

                // Decrement batch remaining_qty if batch is linked
                if ($cart->purchase_item_id && $cart->purchaseItem) {
                    $cart->purchaseItem->decrement('remaining_qty', $qty);
                }

                $totalAmountOrder += $finalTotal;

                /* ================= FBR ITEM ================= */
                $fbrItems[] = [
                    "ItemCode" => $product->sku,
                    "ItemName" => $product->name,
                    "quantity" => $qty,
                    "PCTCode" => $product->PCTCode,
                    "TaxRate" => $taxRate,
                    "SaleValue" => $valueExTax,
                    "TotalAmount" => $finalTotal,
                    "TaxCharged" => $salesTax,
                    "FurtherTax" => $furtherTax,
                    "InvoiceType" => 1,
                ];
            }

            $total = $totalAmountOrder - $orderDiscount;
            $due   = $total - $paid;

            $order->update([
                'sub_total' => $totalAmountOrder,
                'discount' => $orderDiscount,
                'paid' => $paid,
                'total' => $total,
                'due' => $due,
                'status' => $due <= 0
            ]);



            /* ================= FBR PAYLOAD ================= */
            $fbrPayload = [
                "POSID" => 819045,
                "USIN" => "USIN-" . $order->id,
                "DateTime" => now()->format('Y-m-d'),
                "BuyerName" => $customer->name,
                "BuyerPhoneNumber" => $customer->phone,
                "items" => $fbrItems,
                "TotalBillAmount" => $total,
                "InvoiceType" => 1
            ];

            if (!FbrTokenService::isIntegrationEnabled()) {

                DB::commit();

                Order::where('id', $order->id)->update([
                    'type' => 'pos_invoice',
                ]);
        
                PosCart::where('user_id', auth()->id())->delete();
        
                return response()->json([
                    'message' => 'Order completed successfully (FBR Not Integrated)',
                    'order'   => $order,
                ], 200);
            } else {


                $fbrResponse = app(FbrInvoiceService::class)
                    ->postInvoice($fbrPayload);

                if (!$fbrResponse['success']) {

                    DB::rollBack();

                    return response()->json([
                        'error' => 'FBR validation failed',
                    ], 422);
                }

                $order->update([
                    'fbr_invoice_no' => $fbrResponse['invoice_no'],
                    'api_status' => 200,
                    'api_message' => 1,
                    'type' => 'pos_invoice'
                ]);

                PosCart::where('user_id', $userId)->delete();

                DB::commit();

                return response()->json([
                    'message' => 'Order completed successfully',
                    'order'   => $order,
                    'fbr_response' => $fbrResponse
                ], 200);
            }
        } catch (\Exception $e) {

            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function storeDigital(Request $request)
    {
        $request->validate([
            'customer_id'     => ['required', 'exists:customers,id', 'integer'],
            'order_discount'  => ['nullable', 'numeric', 'min:0'],
            'paid'            => ['nullable', 'numeric', 'min:0'],
            'check_duplicate' => ['nullable', 'boolean'],
        ]);

        $business = $request->attributes->get('business');

        $customer = Customer::findOrFail($request->customer_id);

        DB::beginTransaction();

        try {

            $carts = PosCart::with(['product', 'purchaseItem'])
                ->where('user_id', auth()->id())
                ->lockForUpdate()
                ->get();

            if ($carts->isEmpty()) {
                return response()->json(['error' => 'Cart is empty'], 422);
            }

            /* ================= DUPLICATE CHECK ================= */
            if ($request->check_duplicate) {

                $cartProductIds = $carts->pluck('product.id')->toArray();

                $duplicateOrder = Order::where('customer_id', $customer->id)
                    ->where('business_id', $business->id)
                    ->whereHas('products', function ($q) use ($cartProductIds) {
                        $q->whereIn('product_id', $cartProductIds);
                    })
                    ->first();

                if ($duplicateOrder) {
                    return response()->json([
                        'duplicate' => true,
                        'message'   => 'Same order already exists'
                    ]);
                }

                return response()->json(['duplicate' => false]);
            }

            /* ================= CREATE ORDER ================= */

            $order = Order::create([
                'customer_id' => $customer->id,
                'user_id'     => auth()->id(),
                'business_id' => $business->id,
            ]);

            $totalAmountOrder = 0;
            $orderDiscount = $request->order_discount ?? 0;

            $fbrItems = [];

            foreach ($carts as $cart) {

                $product = $cart->product;
                $unitPrice = $cart->price_override ?? $product->discounted_price;
                $qty = $cart->quantity;

                $lineTotal = round($unitPrice * $qty, 2);
                $subTotal  = round($product->price * $qty, 2);
                $discount  = round(($product->price - $unitPrice) * $qty, 2);

                $rateText = trim($product->rate_of_tax);
                $furtherTax = 0;
                $salesTax = 0;

                if (str_ends_with($rateText, '%')) {

                    $rate = floatval(str_replace('%', '', $rateText));
                    $valueExTax = round($lineTotal / (1 + ($rate / 100)), 2);
                    $salesTax  = round($valueExTax * ($rate / 100), 2);

                    if (
                        strtolower($customer->is_registered) === 'unregistered' &&
                        $product->sale_type != '3rd Schedule Goods'
                    ) {
                        $furtherTax = round($valueExTax * 0.04, 2);
                    }
                } else {

                    $salesTax = floatval(preg_replace('/[^0-9.]/', '', $rateText));
                    $valueExTax = round($lineTotal - $salesTax, 2);

                    if (
                        strtolower($customer->is_registered) === 'unregistered' &&
                        $product->sale_type != '3rd Schedule Goods'
                    ) {
                        $furtherTax = round($valueExTax * 0.04, 2);
                    }
                }

                $finalTotal = round($lineTotal + $furtherTax, 2);
                $totalAmountOrder += $finalTotal;

                $order->products()->create([
                    'quantity'       => $qty,
                    'price'          => $unitPrice,
                    'purchase_price' => $cart->purchaseItem ? $cart->purchaseItem->purchase_price : $product->purchase_price,
                    'sub_total'      => $subTotal,
                    'discount'       => $discount,
                    'total'          => $finalTotal,
                    'product_id'     => $product->id,
                    'purchase_item_id' => $cart->purchase_item_id,
                ]);

                $product->decrement('quantity', $qty);

                // Decrement batch remaining_qty if batch is linked
                if ($cart->purchase_item_id && $cart->purchaseItem) {
                    $cart->purchaseItem->decrement('remaining_qty', $qty);
                }

                /* ================= FBR ITEM ================= */

                $fbrItems[] = [
                    "hsCode" => $product->hsCode,
                    "productDescription" => $product->name,
                    "rate" => $rateText,
                    "uoM" => $product->uom,
                    "quantity" => round($qty, 2),
                    "totalValues" => $finalTotal,
                    "valueSalesExcludingST" => $valueExTax,
                    "fixedNotifiedValueOrRetailPrice" => $product->price,
                    "salesTaxApplicable" => $salesTax,
                    "salesTaxWithheldAtSource" => 0,
                    "extraTax" => 0,
                    "furtherTax" => $furtherTax,
                    "sroScheduleNo" => $product->sro_schedule,
                    "fedPayable" => 0,
                    "discount" => 0,
                    "saleType" => $product->sale_type,
                    "sroItemSerialNo" => $product->sro_item,
                ];
            }

            /* ================= FINAL TOTAL ================= */

            $total = $totalAmountOrder - $orderDiscount;
            $paid  = $request->paid ?? 0;
            $due   = $total - $paid;

            $order->update([
                'sub_total' => $totalAmountOrder,
                'discount'  => $orderDiscount,
                'paid'      => $paid,
                'total'     => round($total, 2),
                'due'       => round($due, 2),
                'status'    => $due <= 0,
            ]);

            /* ================= FBR PAYLOAD ================= */

            $fbrPayload = [
                "invoiceType" => "Sale Invoice",
                "invoiceDate" => now()->format('Y-m-d'),
                "sellerNTNCNIC" => $business->cnic,
                "sellerBusinessName" => $business->business_name,
                "sellerProvince" => $business->state,
                "sellerAddress" => $business->address,
                "buyerNTNCNIC" => $customer->ntn_cnic,
                "buyerBusinessName" => $customer->name,
                "buyerProvince" => $customer->province,
                "buyerAddress" => $customer->address,
                "buyerRegistrationType" => $customer->is_registered,
                "invoiceRefNo" => "INV-" . $order->id,
                "scenarioId" => "SN001",
                "items" => $fbrItems
            ];
            // for non-integrated fbr 
            if (!FbrTokenService::isIntegrationEnabled()) {

                DB::commit();
        
                PosCart::where('user_id', auth()->id())->delete();
        
                return response()->json([
                    'message' => 'Order completed successfully (FBR Not Integrated)',
                    'order'   => $order,
                ], 200);
            } else {

                $tokens = FbrTokenService::getToken();

                if (is_array($tokens)) {
                    $token = $tokens['digital_token'];
                } else {
                    $token = $tokens;
                }

                try {
                    $response = Http::withOptions([
                        'timeout' => 620,
                        'connect_timeout' => 620,
                    ])->withHeaders([
                        'Authorization' => "Bearer $token",
                        'Content-Type' => 'application/json',
                        'Accept-Encoding' => 'gzip, deflate, br',
                        'Accept' => 'application/json'
                    ])->post("https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata_sb", $fbrPayload);
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    info('Connection Exception: ' . $e->getMessage());
                }

                $responseJson = $response->json();

                $statusCode = data_get($responseJson, 'validationResponse.statusCode');
                $errorCode  = data_get($responseJson, 'validationResponse.errorCode');
                $errorMsg   = data_get($responseJson, 'validationResponse.error');

                $order->update([
                    'api_status'    => $response->status(),
                    'error_code'    => $errorCode,
                    'error_message' => $errorMsg,
                    'api_message'   => $response->ok(),
                ]);

                if ($statusCode != "00") {

                    DB::rollBack();

                    return response()->json([
                        'error' => 'FBR validation failed',
                        'error_code' => $errorCode,
                        'error_message' => $errorMsg,
                        'fbr_response' => $responseJson
                    ], 422);
                }

                DB::commit();

                /* ================= POST INVOICE ================= */

                try {
                    $post_api_response = Http::withOptions([
                        'timeout' => 620,
                        'connect_timeout' => 620,

                    ])->withHeaders([
                        'Authorization' => "Bearer $token",
                        'Content-Type' => 'application/json',
                        'Accept-Encoding' => 'gzip, deflate, br',
                        'Accept' => 'application/json'
                    ])->post("https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb", $fbrPayload);
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    info('Connection Exception: ' . $e->getMessage());
                }

                $postResponseJson = $post_api_response->json();

                $invoiceNumber = data_get($postResponseJson, 'invoiceNumber');

                $order->update([
                    'fbr_invoice_no' => $invoiceNumber,
                ]);

                PosCart::where('user_id', auth()->id())->delete();

                return response()->json([
                    'message' => 'Order completed successfully',
                    'order'   => $order,
                    'fbr_invoice_no' => $invoiceNumber
                ], 200);
            }
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'error' => 'Order failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
