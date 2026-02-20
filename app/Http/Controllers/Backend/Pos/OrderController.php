<?php

namespace App\Http\Controllers\Backend\Pos;

use App\Http\Controllers\Controller;
use App\Jobs\SendFBRInvoiceJob;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\OrderProduct;
use App\Models\OrderTransaction;
use App\Models\PosCart;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {
       
        if ($request->ajax()) {
            $businessId = session('business_id');
            if (!$businessId) {
                return response()->json(['error' => 'No business selected'], 422);
            }
            $orders = Order::with('customer')
                ->where('business_id', $businessId)
                ->get();
                $orderIds = $orders->pluck('id');


            $orderProducts = OrderProduct::select('product_id')->whereIn('order_id', $orderIds)->get();

            $orderProductsIds = $orderProducts->pluck('product_id');
            $purchase_id = PurchaseItem::select('purchase_id')->whereIn('product_id', $orderProductsIds)->get();
           
            $purchaseIds = $purchase_id->pluck('purchase_id');

           
            return DataTables::of($orders)
                ->addIndexColumn()
                ->addColumn('saleId', fn($data) => "#" . $data->id)
                ->addColumn('customer', fn($data) => $data->customer->name ?? '-')
                ->addColumn('item', fn($data) => $data->total_item)
                ->addColumn('sub_total', fn($data) => number_format($data->sub_total, 2, '.', ','))
                ->addColumn('discount', fn($data) => number_format($data->discount, 2, '.', ','))
                ->addColumn('total', fn($data) => number_format($data->total, 2, '.', ','))
                ->addColumn('paid', fn($data) => number_format($data->paid, 2, '.', ','))
                ->addColumn('due', fn($data) => number_format($data->due, 2, '.', ','))
                // ->addColumn('stock_total', fn($data) => number_format($stockTotal->sum('grand_total'), 2, '.', ','))
                ->addColumn('status', fn($data) => $data->status
                    ? '<span class="badge bg-primary">Paid</span>'
                    : '<span class="badge bg-danger">Due</span>')
                
                ->addColumn('fbr_status', function ($d) {
                    if ($d->api_status == 200 && (empty($d->error_code) || $d->error_code == null)) {
                        return "<button data-id=$d->id class='fbr-status btn btn-md bg-white' title='Data Submitted'><i class='fas fa-check-circle text-success'></i></button>";
                    }
                    if ($d->api_status == 200 && (!empty($d->error_code) || $d->error_code != null) ) {
                        return "<button data-id=$d->id class='fbr-status btn btn-md bg-white' title='Data Not Correct For FBR'><i class='fas fa-times-circle text-danger'></i></button>";
                    }
                    if ($d->api_status != 200) {
                        return "<button data-id=$d->id class='fbr-status btn btn-md bg-white' title='Api Faild' ><i class='fas fa-exclamation-triangle text-warning'></i></button>";
                    }
                })
                ->addColumn('action', function ($data) {
                    $buttons = '';

                    $buttons .= '<a class="btn btn-success btn-sm" href="' . route('backend.admin.orders.invoice', $data->id) . '"><i class="fas fa-file-invoice"></i> Invoice</a>';

                    $buttons .= '<a class="btn btn-secondary btn-sm" href="' . route('backend.admin.orders.pos-invoice', $data->id) . '"><i class="fas fa-file-invoice"></i> Pos Invoice</a>';
                    if (!$data->status) {
                        $buttons .= '<a class="btn btn-warning btn-sm" href="' . route('backend.admin.due.collection', $data->id) . '"><i class="fas fa-receipt"></i> Due Collection</a>';
                    }
                    $buttons .= '<a class="btn btn-primary btn-sm" href="' . route('backend.admin.orders.transactions', $data->id) . '"><i class="fas fa-exchange-alt"></i> Transactions</a>';
                    return $buttons;
                })
                ->rawColumns(['saleId', 'customer', 'item', 'sub_total', 'discount', 'total', 'paid', 'due', 'status', 'fbr_status' , 'action'])
                ->toJson();
        }
        return view('backend.orders.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */


//     


// <<<<<<<<<<<<<<<<<<<<< first sennd fbi ten save in  >>>>>>>>>>>>>>>>>>>>>

public function store(Request $request)
{
    $request->validate([
        'customer_id' => ['required', 'exists:customers,id', 'integer'],
        'order_discount' => ['nullable', 'numeric', 'min:0'],
        'paid' => ['nullable', 'numeric', 'min:0'],
    ]);

    $businessId = session('business_id');

    if (!$businessId) {
        return response()->json(['error' => 'No business selected'], 422);
    }

    $customer = Customer::find($request->customer_id);


    $carts = PosCart::with('product')
        ->where('user_id', auth()->id())
        ->lockForUpdate()
        ->get();

    if ($carts->isEmpty()) {
        return response()->json(['error' => 'Cart is empty'], 422);
    }

    /* ================= DUPLICATE CHECK ================= */
    if ($request->check_duplicate) {

        $cartProductIds = $carts->pluck('product.id')->toArray();

        $duplicateOrder = Order::where('customer_id', $request->customer_id)
            ->where('business_id', $businessId)
            ->whereHas('products', function ($q) use ($cartProductIds) {
                $q->whereIn('product_id', $cartProductIds);
            })
            ->first();

        if ($duplicateOrder) {
            return response()->json([
                'duplicate' => true,
                'message' => 'Same order already exists. Do you want to create it again?',
            ]);
        }

        return response()->json(['duplicate' => false]);
    }

    /* ================= ORDER TRANSACTION ================= */
    DB::beginTransaction();

    $order = Order::create([
        'customer_id' => $request->customer_id,
        'user_id'     => auth()->id(),
        'business_id' => $businessId,
    ]);

    $totalAmountOrder = 0;
    $orderDiscount = $request->order_discount ?? 0;


    foreach ($carts as $cart) {
    $product = $cart->product;

    // Use price_override if exists
    $unitPrice = $cart->price_override ?? $product->discounted_price;
    $qty = $cart->quantity;
    $lineTotal = round($unitPrice * $qty, 2);
    $subTotal  = round($product->price * $qty, 2);
    $discount  = round(($product->price - $unitPrice) * $qty, 2);

   // 🔹 FURTHER TAX (NEW)
        $rateText = trim($product->rate_of_tax);
        // $furtherTax = 0;

        if (str_ends_with($rateText, '%')) {
            $rate = floatval(str_replace('%', '', $rateText));
            $valueExTax = round($lineTotal / (1 + ($rate / 100)), 2);

            // if (
            //     $customer->is_registered &&
            //     strtolower($customer->is_registered) === 'unregistered' &&
            //     $product->sale_type != '3rd Schedule Goods'
            // ) {
            //     $furtherTax = round($valueExTax * 0.04, 2);
            // }
        }

        // ✅ FINAL TOTAL = line total + further tax
        $finalTotal = round($lineTotal , 2);

    $totalAmountOrder += $finalTotal;

    $order->products()->create([
        'quantity'       => $cart->quantity,
        'price'          => $unitPrice,  // ✅ override price here
        'purchase_price' => $product->purchase_price,
        'sub_total'      => $subTotal,
        'discount'       => $discount,
        'total'          => $finalTotal,
        'product_id'     => $product->id,
    ]);

    $product->quantity -= $cart->quantity;
    $product->save();
}

    $total = $totalAmountOrder - $orderDiscount;
    $due   = $total - ($request->paid ?? 0);

    $order->update([
        'sub_total' => $totalAmountOrder,
        'discount'  => $orderDiscount,
        'paid'      => $request->paid,
        'total'     => round($total, 2),
        'due'       => round($due, 2),
        'status'    => round($due, 2) <= 0,
    ]);

    /* ================= FBR ITEMS ================= */
    $fbrItems = [];
    // $furtherTax = 0;
    $lineTotal = 0;
    foreach ($carts as $cart) {
        $product = $cart->product;
        

        $qty = round($cart->quantity, 2);
        // $lineTotal = round($product->discounted_price * $qty, 2);
        $unitPrice = $cart->price_override ?? $product->discounted_price;
        $lineTotal = round($unitPrice * $qty, 2);

        $rateText = trim($product->rate_of_tax); // 18%
        $taxRate  = (float) str_replace('%', '', $rateText); // 18
        if (str_ends_with($rateText, '%')) {
            $rate = floatval(str_replace('%', '', $rateText));
            $valueExTax = round($lineTotal / (1 + ($rate / 100)), 2);
            // $salesTax = round($lineTotal - $valueExTax, 2);
            $salesTax  = round($valueExTax * ($rate / 100), 2);

            // if($customer->is_registered && strtolower($customer->is_registered) === 'unregistered' && $product->sale_type != '3rd Schedule Goods'){
            //     $furtherTax = round($valueExTax * (4 / 100), 2);
  
            // }else{
            //     $furtherTax = 0;
            // }

        } else {
            $salesTax = floatval(preg_replace('/[^0-9.]/', '', $rateText));
            $valueExTax = round($lineTotal - $salesTax, 2);

            //  if($customer->is_registered && strtolower($customer->is_registered) === 'unregistered' && $product->sale_type != '3rd Schedule Goods'){
            //     $furtherTax = round($valueExTax * (4 / 100), 2);
  
            // }else{
            //     $furtherTax = 0;
            // }
        }


       
        $fbrItems[] = [
            // "hsCode" => $product->hsCode,
            // "productDescription" => $product->name,
            // "rate" => $rateText,
            // "uoM" => $product->uom
            // "quantity" => $qty,
            // "totalValues" => $lineTotal + $furtherTax,
            // "valueSalesExcludingST" => $valueExTax,
            // "fixedNotifiedValueOrRetailPrice" => $product->price,
            // "salesTaxApplicable" => $salesTax,
            // "salesTaxWithheldAtSource" => 0,
            // "extraTax" => 0,
            // "furtherTax" => $furtherTax,
            // "sroScheduleNo" => $product->sro_schedule,
            // "fedPayable" => 0,
            "ItemCode"=> $product->sku,
            "ItemName" => $product->name,
            "quantity" => $qty,
            "PCTCode" => "11001010",
            "TaxRate" => $taxRate,
            "SaleValue" => $valueExTax,
            "TotalAmount" => $lineTotal ,//+ $furtherTax,
            "TaxCharged" => $salesTax,
            "discount" => 0,
            //  "FurtherTax" => $furtherTax,
             "InvoiceType" => 1,
            // "saleType" => $product->sale_type,
            // "sroItemSerialNo" => $product->sro_item,
        ];
    }

    $customer = Customer::find($request->customer_id);
    $business = Business::find($businessId);

    $fbrPayload = [
        "POSID" => 819045,
        "USIN" => "USIN0",
        "DateTime" => now()->format('Y-m-d'),
        // "invoiceType" => "Sale Invoice",
        // "invoiceDate" => now()->format('Y-m-d'),
        // "sellerNTNCNIC" => $business->cnic,
        // "sellerBusinessName" => $business->business_name,
        // "sellerProvince" => $business->state,
        // "sellerAddress" => $business->address,
        "buyerNTN" => $customer->ntn_cnic,
        "buyerCNIC" => $customer->ntn_cnic,
        "BuyerName" => $customer->name,
         "BuyerPhoneNumber" => $customer->phone,
        // "buyerBusinessName" => $customer->name,
        // "buyerProvince" => $customer->province,
        // "buyerAddress" => $customer->address,
        // "buyerRegistrationType" => $customer->is_registered,
        // "invoiceRefNo" => "INV-" . $order->id,
        // "invoiceRefNo" => "INV-" . "INV00112200",
        // "scenarioId" => "SN015",
        "items" => $fbrItems,

        "TotalBillAmount" => $lineTotal  ,
        "TotalQuantity" => $qty,
        "TotalSaleValue" => $valueExTax,
        "TotalTaxCharged" => $salesTax,
        "PaymentMode" => 2,
        "InvoiceType" => 1
    ];

    /* ================= FBR API CALL ================= */
    Log::info('FBR Payload', [
    'order_id' => $order->id ?? null,
    'payload'  => $fbrPayload
]);

    // $token = env('FBR_TOKEN');
    // try {
    //     $response = Http::withOptions([
    //         'timeout' => 620,
    //         'connect_timeout' => 620,
    //     ])->withHeaders([
    //         'Authorization' => "Bearer $token",
    //         'Content-Type' => 'application/json',
    //         'Accept-Encoding' => 'gzip, deflate, br',
    //         'Accept' => 'application/json'
    //     ])->post("https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata_sb", $fbrPayload);

    // } catch (\Illuminate\Http\Client\ConnectionException $e) {
    //     info('Connection Exception: ' . $e->getMessage());
    // }

    
    // $responseJson = $response->json();
    
    // $statusCode = data_get($responseJson, 'validationResponse.statusCode');
    // $errorCode = data_get($responseJson, 'validationResponse.errorCode');

   
    // $errorMessage = data_get($responseJson, 'validationResponse.error')
    //     ?? data_get($responseJson, 'error');

    /* ================= SAVE API STATUS & LOGS (NO ROLLBACK) ================= */
    // Order::where('id', $order->id)->update([
    //     'api_status'    => $response->status(),
    //     'error_code'    => $errorCode,
    //     'error_message' => $errorMessage,
    //     'api_message'   => $response->ok(),
    // ]);


    /* ================= FINAL DECISION ================= */
    // if ($statusCode != "00") {

    //     DB::rollBack();

        // return response()->json([
        //     'error' => 'FBR validation failed',
        //     'error_code' => $errorCode,
        //     'error_message' => $errorMessage,
        //     'fbr_api_response' => $responseJson,
        // ]);
    // }


    $post_api_response=null;
     try {
            // $post_api_response = Http::withOptions([
            //     'timeout' => 620,
            //     'connect_timeout' => 620,
            // ])->withHeaders([
            //     'Authorization' => "Bearer $token",
            //     'Content-Type' => 'application/json',
            //     'Accept-Encoding' => 'gzip, deflate, br',
            //     'Accept' => 'application/json'
            // ])->post("https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb", $fbrPayload);


            $post_api_response = Http::withOptions([
                'timeout' => 620,
                'connect_timeout' => 620,
                'verify' => false,
                //   CURLOPT_SSL_VERIFYHOST => false,
                // CURLOPT_SSL_VERIFYPEER => false,
            ])->withHeaders([
                'Authorization' => "Bearer 1298b5eb-b252-3d97-8622-a4a69d5bf818",
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept' => 'application/json'
            ])->post("https://esp.fbr.gov.pk:8244/FBR/v1/api/Live/PostData", $fbrPayload);
    //         ])->post("https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb", $fbrPayload);
    

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // info('Connection Exception: ' . $e->getMessage());
            dd($e->getMessage()); 
        }


        $postResponseJson = $post_api_response->json();

        // dd($postResponseJson['Response']);
         
        
        $fbr_invoice_number = data_get($postResponseJson, 'InvoiceNumber');


        if($postResponseJson['Response'] == "Invoice received successfully"){
            DB::commit(); // save order data in db now 

            Order::where('id', $order->id)->update([
                'fbr_invoice_no'    => $fbr_invoice_number,
                 'api_status'    => $post_api_response->status(),
                'api_message'   => 1,
                'type' => 'pos_invoice',
            ]);


        }else{
             DB::rollBack();

            return response()->json([
                'error' => $postResponseJson->Response,
                'error_code' => $postResponseJson->Code ?? null,
                'error_message' => $postResponseJson->Message ?? null,
                'fbr_api_response' => $postResponseJson,
            ]);
        }
        
            /* ================= SAVE API STATUS & LOGS (NO ROLLBACK) ================= */
            // Order::where('id', $order->id)->update([
            //     'fbr_invoice_no'    => $fbr_invoice_number,
            // ]);


             // Order::where('id', $order->id)->update([
    //     'api_status'    => $response->status(),
    //     'error_code'    => $errorCode,
    //     'error_message' => $errorMessage,
    //     'api_message'   => $response->ok(),
    // ]);
    

    PosCart::where('user_id', auth()->id())->delete();

    return response()->json([
        'message' => 'Order completed successfully!',
        'fbr_payload_sent' => $fbrPayload,
        'fbr_api_response' => $postResponseJson,
        'order' => $order
    ], 200);
}


public function storeDigital(Request $request)
{
    $request->validate([
        'customer_id' => ['required', 'exists:customers,id', 'integer'],
        'order_discount' => ['nullable', 'numeric', 'min:0'],
        'paid' => ['nullable', 'numeric', 'min:0'],
    ]);

    $businessId = session('business_id');

    if (!$businessId) {
        return response()->json(['error' => 'No business selected'], 422);
    }

    $customer = Customer::find($request->customer_id);


    $carts = PosCart::with('product')
        ->where('user_id', auth()->id())
        ->lockForUpdate()
        ->get();

    if ($carts->isEmpty()) {
        return response()->json(['error' => 'Cart is empty'], 422);
    }

    /* ================= DUPLICATE CHECK ================= */
    if ($request->check_duplicate) {

        $cartProductIds = $carts->pluck('product.id')->toArray();

        $duplicateOrder = Order::where('customer_id', $request->customer_id)
            ->where('business_id', $businessId)
            ->whereHas('products', function ($q) use ($cartProductIds) {
                $q->whereIn('product_id', $cartProductIds);
            })
            ->first();

        if ($duplicateOrder) {
            return response()->json([
                'duplicate' => true,
                'message' => 'Same order already exists. Do you want to create it again?',
            ]);
        }

        return response()->json(['duplicate' => false]);
    }

    /* ================= ORDER TRANSACTION ================= */
    DB::beginTransaction();

    $order = Order::create([
        'customer_id' => $request->customer_id,
        'user_id'     => auth()->id(),
        'business_id' => $businessId,
    ]);

    $totalAmountOrder = 0;
    $orderDiscount = $request->order_discount ?? 0;


    foreach ($carts as $cart) {
    $product = $cart->product;

    // Use price_override if exists
    $unitPrice = $cart->price_override ?? $product->discounted_price;
    $qty = $cart->quantity;
    $lineTotal = round($unitPrice * $qty, 2);
    $subTotal  = round($product->price * $qty, 2);
    $discount  = round(($product->price - $unitPrice) * $qty, 2);

   // 🔹 FURTHER TAX (NEW)
        $rateText = trim($product->rate_of_tax);
        $furtherTax = 0;

        if (str_ends_with($rateText, '%')) {
            $rate = floatval(str_replace('%', '', $rateText));
            $valueExTax = round($lineTotal / (1 + ($rate / 100)), 2);

            if (
                $customer->is_registered &&
                strtolower($customer->is_registered) === 'unregistered' &&
                $product->sale_type != '3rd Schedule Goods'
            ) {
                $furtherTax = round($valueExTax * 0.04, 2);
            }
        }

        // ✅ FINAL TOTAL = line total + further tax
        $finalTotal = round($lineTotal + $furtherTax, 2);

    $totalAmountOrder += $finalTotal;

    $order->products()->create([
        'quantity'       => $cart->quantity,
        'price'          => $unitPrice,  // ✅ override price here
        'purchase_price' => $product->purchase_price,
        'sub_total'      => $subTotal,
        'discount'       => $discount,
        'total'          => $finalTotal,
        'product_id'     => $product->id,
    ]);

    $product->quantity -= $cart->quantity;
    $product->save();
}

    $total = $totalAmountOrder - $orderDiscount;
    $due   = $total - ($request->paid ?? 0);

    $order->update([
        'sub_total' => $totalAmountOrder,
        'discount'  => $orderDiscount,
        'paid'      => $request->paid,
        'total'     => round($total, 2),
        'due'       => round($due, 2),
        'status'    => round($due, 2) <= 0,
    ]);

    /* ================= FBR ITEMS ================= */
    $fbrItems = [];
    $furtherTax = 0;
    $lineTotal = 0;
    foreach ($carts as $cart) {
        $product = $cart->product;
        

        $qty = round($cart->quantity, 2);
        // $lineTotal = round($product->discounted_price * $qty, 2);
        $unitPrice = $cart->price_override ?? $product->discounted_price;
        $lineTotal = round($unitPrice * $qty, 2);

        $rateText = trim($product->rate_of_tax);

        if (str_ends_with($rateText, '%')) {
            $rate = floatval(str_replace('%', '', $rateText));
            $valueExTax = round($lineTotal / (1 + ($rate / 100)), 2);
            // $salesTax = round($lineTotal - $valueExTax, 2);
            $salesTax  = round($valueExTax * ($rate / 100), 2);

            if($customer->is_registered && strtolower($customer->is_registered) === 'unregistered' && $product->sale_type != '3rd Schedule Goods'){
                $furtherTax = round($valueExTax * (4 / 100), 2);
  
            }else{
                $furtherTax = 0;
            }

        } else {
            $salesTax = floatval(preg_replace('/[^0-9.]/', '', $rateText));
            $valueExTax = round($lineTotal - $salesTax, 2);

             if($customer->is_registered && strtolower($customer->is_registered) === 'unregistered' && $product->sale_type != '3rd Schedule Goods'){
                $furtherTax = round($valueExTax * (4 / 100), 2);
  
            }else{
                $furtherTax = 0;
            }
        }

        $fbrItems[] = [
            "hsCode" => $product->hsCode,
            "productDescription" => $product->name,
            // "productDescription" => $product->hs_description,
            "rate" => $rateText,
            "uoM" => $product->uom,
            "quantity" => $qty,
            "totalValues" => $lineTotal + $furtherTax,
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

    $customer = Customer::find($request->customer_id);
    $business = Business::find($businessId);

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
        // "invoiceRefNo" => "INV-" . "INV00112200",
        "scenarioId" => "SN015",
        "items" => $fbrItems
    ];

    /* ================= FBR API CALL ================= */
    Log::info('FBR Payload', [
    'order_id' => $order->id ?? null,
    'payload'  => $fbrPayload
]);

    $token = env('FBR_TOKEN');
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
    $errorCode = data_get($responseJson, 'validationResponse.errorCode');

   
    $errorMessage = data_get($responseJson, 'validationResponse.error')
        ?? data_get($responseJson, 'error');

    /* ================= SAVE API STATUS & LOGS (NO ROLLBACK) ================= */
    Order::where('id', $order->id)->update([
        'api_status'    => $response->status(),
        'error_code'    => $errorCode,
        'error_message' => $errorMessage,
        'api_message'   => $response->ok(),
    ]);


    /* ================= FINAL DECISION ================= */
    if ($statusCode != "00") {

        DB::rollBack();

        return response()->json([
            'error' => 'FBR validation failed',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'fbr_api_response' => $responseJson,
        ]);
    }


    DB::commit();
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

        
        $fbr_invoice_number = data_get($postResponseJson, 'invoiceNumber');
        
            /* ================= SAVE API STATUS & LOGS (NO ROLLBACK) ================= */
            Order::where('id', $order->id)->update([
                'fbr_invoice_no'    => $fbr_invoice_number,
            ]);

    

    PosCart::where('user_id', auth()->id())->delete();

    return response()->json([
        'message' => 'Order completed successfully!',
        'fbr_payload_sent' => $fbrPayload,
        'fbr_api_response' => $responseJson,
        // 'order' => $order
    ], 200);
}

// <<<<<<<<<<<<<<< some changes add dublicate check init >>>>>>>>>>>>>>>>>>>
//     public function store(Request $request)
// {
//     $request->validate([
//         'customer_id' => [
//             'required',
//             'exists:customers,id',
//             'integer',
//         ],
//         'order_discount' => [
//             'nullable',
//             'numeric',
//             'min:0',
//         ],
//         'paid' => [
//             'nullable',
//             'numeric',
//             'min:0',
//         ],
//     ], [
//         'customer_id.required' => 'Please select a customer.',
//         'customer_id.exists'   => 'The selected customer does not exist.',
//         'order_discount.numeric' => 'The order discount must be a number.',
//         'paid.numeric'           => 'The amount paid must be a number.',
//     ]);

//     $businessId = session('business_id'); // yahan se business_id mil raha hai

//     if (!$businessId) {
//         return response()->json(['error' => 'No business selected'], 422);
//     }

//     $carts = PosCart::with('product')->where('user_id', auth()->id())->get();

//     if ($carts->isEmpty()) {
//         return response()->json(['error' => 'Cart is empty'], 422);
//     }

//     // ===============================
//     // DUPLICATE ORDER CHECK
//     // ===============================
//     if ($request->check_duplicate) {
//         $cartProductIds = $carts->pluck('product.id')->toArray();

//         $duplicateOrder = Order::where('customer_id', $request->customer_id)
//             ->where('business_id', $businessId)
//             ->whereHas('products', function ($q) use ($cartProductIds) {
//                 $q->whereIn('product_id', $cartProductIds);
//             })
//             ->first();

//         if ($duplicateOrder) {
//             return response()->json([
//                 'duplicate' => true,
//                 'message' => 'Same order already exists. Do you want to create it again?',
//             ]);
//         }

//         return response()->json([
//             'duplicate' => false
//         ]);
//     }

//     // order create karte waqt business_id bhi insert hoga
//     $order = Order::create([
//         'customer_id' => $request->customer_id,
//         'user_id'     => $request->user()->id,
//         'business_id' => $businessId,
//     ]);

//     $totalAmountOrder = 0;
//     $orderDiscount = $request->order_discount;

//     foreach ($carts as $cart) {
//         $product = $cart->product;
//         $mainTotal = $product->price * $cart->quantity;
//         $totalAfterDiscount = $product->discounted_price * $cart->quantity;
//         $discount = $mainTotal - $totalAfterDiscount;
//         $totalAmountOrder += $totalAfterDiscount;

//         $order->products()->create([
//             'quantity'       => $cart->quantity,
//             'price'          => $cart->product->price,
//             'purchase_price' => $cart->product->purchase_price,
//             'sub_total'      => $mainTotal,
//             'discount'       => $discount,
//             'total'          => $totalAfterDiscount,
//             'product_id'     => $cart->product->id,
//         ]);

//         $cart->product->quantity -= $cart->quantity;
//         $cart->product->save();
//     }

//     $total = $totalAmountOrder - $orderDiscount;
//     $due   = $total - $request->paid;

//     $order->sub_total = $totalAmountOrder;
//     $order->discount  = $orderDiscount;
//     $order->paid      = $request->paid;
//     $order->total     = round((float)$total, 2);
//     $order->due       = round((float)$due, 2);
//     $order->status    = round((float)$due, 2) <= 0;
//     $order->save();

//     // BUILD FBR ITEMS ARRAY
//     $fbrItems = [];

//     foreach ($carts as $cart) {
//         $product = $cart->product;
//         $quantity = round($cart->quantity, 4);
//         $lineTotal = round($product->discounted_price * $quantity, 2); // 2 decimals
//         $rate_of_taxx = $product->rate_of_tax;
//         $rate_of_tax = trim($rate_of_taxx);

//         if (str_ends_with($rate_of_tax, '%')) {
//             $numericRate = floatval(str_replace('%', '', $rate_of_tax));
//             $valueExTax = round($lineTotal / (1 + ($numericRate / 100)), 2);
//             $salesTax   = round($lineTotal - $valueExTax, 2);
//         } elseif (str_starts_with($rate_of_tax, 'Rs')) {
//             $numericRate = floatval(preg_replace('/[^0-9.]/', '', $rate_of_tax));
//             $salesTax   = $numericRate;
//             $valueExTax = round($lineTotal - $salesTax, 2);
//         } else {
//             $numericRate = floatval($rate_of_tax);
//             $valueExTax = round($lineTotal / (1 + ($numericRate / 100)), 2);
//             $salesTax   = round($lineTotal - $valueExTax, 2);
//         }

//         $hsCode = $product->hsCode;
//         $hs_desc = $product->hs_description;
//         $uom = $product->uom;
//         $sro_item = $product->sro_item;
//         $sale_type = $product->sale_type;
//         $sro_schedule = $product->sro_schedule;

//         $fbrItems[] = [
//             "hsCode" => $hsCode,
//             "productDescription" => $hs_desc,
//             "rate" => $rate_of_taxx,
//             "uoM" => $uom,
//             "quantity" => (float) number_format($quantity, 4, '.', ''),
//             "totalValues" => (float) number_format($lineTotal, 2, '.', ''),
//             "valueSalesExcludingST" => (float) number_format($valueExTax, 2, '.', ''),
//             "fixedNotifiedValueOrRetailPrice" => 0,
//             "salesTaxApplicable" => (float) number_format($salesTax, 2, '.', ''),
//             "salesTaxWithheldAtSource" => (float) number_format($cart->salesTaxWithheldAtSource, 2, '.', ''),
//             "extraTax" => 0.00,
//             "furtherTax" => 0,
//             "sroScheduleNo" => $sro_schedule,
//             "fedPayable" => 0,
//             "discount" => (float) number_format($discount, 2, '.', ''),
//             "saleType" => $sale_type,
//             "sroItemSerialNo" => $sro_item,
//         ];
//     }

//     // BUILD FBR PAYLOAD
//     $customer = Customer::find($request->customer_id);
//     $businessData = Business::find($businessId);

//     $fbrPayload = [
//         "invoiceType" => "Sale Invoice",
//         "invoiceDate" => now()->format('Y-m-d'),
//         "sellerNTNCNIC" => $businessData->cnic,
//         "sellerBusinessName" => $businessData->business_name,
//         "sellerProvince" => $businessData->state,
//         "sellerAddress" => $businessData->address,
//         "buyerNTNCNIC" => "",
//         "buyerBusinessName" => $customer->name,
//         "buyerProvince" => $customer->province ?? "Punjab",
//         "buyerAddress" => $customer->address ?? "",
//         "buyerRegistrationType" => $customer->is_registered ? "Registered" : "Unregistered",
//         "invoiceRefNo" => "INV-" . $order->id,
//         "scenarioId" => "SN002",
//         "items" => $fbrItems
//     ];

//     // SEND TO FBR API
//     $token = env('FBR_TOKEN');

//     try {
//         $response = Http::withOptions([
//             'timeout' => 620,
//             'connect_timeout' => 620,
//         ])->withHeaders([
//             'Authorization' => "Bearer $token",
//             'Content-Type' => 'application/json',
//             'Accept-Encoding' => 'gzip, deflate, br',
//             'Accept' => 'application/json'
//         ])->post("https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata_sb", $fbrPayload);

//     } catch (\Illuminate\Http\Client\ConnectionException $e) {
//         info('Connection Exception: ' . $e->getMessage());
//     }

//     // RECORD PAYMENT (IF ANY)
//     if ($request->paid > 0) {
//         $order->transactions()->create([
//             'amount'      => $request->paid,
//             'customer_id' => $order->customer_id,
//             'user_id'     => auth()->id(),
//             'paid_by'     => 'cash',
//         ]);
//     }

//     PosCart::where('user_id', auth()->id())->delete();

//     $responseJson = $response->json();

//     $errorCode = data_get($responseJson, 'validationResponse.invoiceStatuses.0.errorCode') 
//         ?? data_get($responseJson, 'errorCode');

//     $errorMessage = data_get($responseJson, 'validationResponse.invoiceStatuses.0.error') 
//         ?? data_get($responseJson, 'error');

//     Order::where('id', $order->id)->update([            
//         'api_status' => $response->status(),
//         'error_code' => $errorCode,
//         'error_message' => $errorMessage,
//         'api_message' => $response->ok(),
//     ]);

//     OrderLog::create([
//         'order_id'       => $order->id,
//         'request_payload'=> $fbrPayload,
//         'response'       => $responseJson,
//         'status_code'    => $response->status(),
//         'error_code'     => $errorCode,
//         'error_message'  => $errorMessage,
//         'success'        => $response->ok(),
//     ]);

//     Log::channel('fbr')->info('FBR API Request', [
//         'order_id' => $order->id,
//         'payload'  => $fbrPayload
//     ]);

//     Log::channel('fbr')->info('FBR API Response', [
//         'order_id'       => $order->id,
//         'response'       => $responseJson,
//         'status_code'    => $response->status(),
//         'error_code'     => $errorCode,
//         'error_message'  => $errorMessage,
//         'success'        => $response->ok(),
//     ]);

//     // FINAL RESPONSE
//     return response()->json([
//         'message' => 'Order completed successfully!',
//         'fbr_payload_sent' => $fbrPayload,
//         'fbr_api_response' => $response->json(),
//         'fbr_api_response_statu' => $response->status(),
//         'fbr_api_response_message' => $response->ok(),
//     ], 200);
// }

// <<<<<<<<<<<<<<<<<<<< my orignal fbr code and store code >>>>>>>>>>>>>>>>>>>>

//     public function store(Request $request)
// {
//     $request->validate([
//         'customer_id' => [
//             'required',
//             'exists:customers,id',
//             'integer',
//         ],
//         'order_discount' => [
//             'nullable',
//             'numeric',
//             'min:0',
//         ],
//         'paid' => [
//             'nullable',
//             'numeric',
//             'min:0',
//         ],
//     ], [
//         'customer_id.required' => 'Please select a customer.',
//         'customer_id.exists'   => 'The selected customer does not exist.',
//         'order_discount.numeric' => 'The order discount must be a number.',
//         'paid.numeric'           => 'The amount paid must be a number.',
//     ]);

//     $businessId = session('business_id'); // yahan se business_id mil raha hai

//     if (!$businessId) {
//         return response()->json(['error' => 'No business selected'], 422);
//     }

//     $carts = PosCart::with('product')->where('user_id', auth()->id())->get();

    

//     if ($carts->isEmpty()) {
//         return response()->json(['error' => 'Cart is empty'], 422);
//     }
//     // order create karte waqt business_id bhi insert hoga
//     $order = Order::create([
//         'customer_id' => $request->customer_id,
//         'user_id'     => $request->user()->id,
//         'business_id' => $businessId,
//     ]);


//     $totalAmountOrder = 0;
//     $orderDiscount = $request->order_discount;

//     foreach ($carts as $cart) {
//         $product = $cart->product;
//         $mainTotal = $product->price * $cart->quantity;
//         $totalAfterDiscount = $product->discounted_price * $cart->quantity;
//         $discount = $mainTotal - $totalAfterDiscount;
//         $totalAmountOrder += $totalAfterDiscount;

//         $order->products()->create([
//             'quantity'       => $cart->quantity,
//             'price'          => $cart->product->price,
//             'purchase_price' => $cart->product->purchase_price,
//             'sub_total'      => $mainTotal,
//             'discount'       => $discount,
//             'total'          => $totalAfterDiscount,
//             'product_id'     => $cart->product->id,
//         ]);

//         $cart->product->quantity -= $cart->quantity;
//         $cart->product->save();
//     }

//     $total = $totalAmountOrder - $orderDiscount;
//     $due   = $total - $request->paid;

//     $order->sub_total = $totalAmountOrder;
//     $order->discount  = $orderDiscount;
//     $order->paid      = $request->paid;
//     $order->total     = round((float)$total, 2);
//     $order->due       = round((float)$due, 2);
//     $order->status    = round((float)$due, 2) <= 0;
//     $order->save();

//      // BUILD FBR ITEMS ARRAY
//      $fbrItems = [];

//      foreach ($carts as $cart) {
        
//         $product = $cart->product;
//         // fbr errror for 2 decimal 
//         $quantity = round($cart->quantity, 4); 
//         $lineTotal = round($product->discounted_price * $quantity, 2); // 2 decimals
//         $rate_of_taxx = $product->rate_of_tax;
//         $rate_of_tax = trim($rate_of_taxx); // Remove spaces

//         if (str_ends_with($rate_of_tax, '%')) {
//             // Percentage case
//             $numericRate = floatval(str_replace('%', '', $rate_of_tax));

//             $valueExTax = round($lineTotal / (1 + ($numericRate / 100)), 2);
//             $salesTax   = round($lineTotal - $valueExTax, 2);

//         } elseif (str_starts_with($rate_of_tax, 'Rs')) {
//             // Fixed amount case
//             $numericRate = floatval(preg_replace('/[^0-9.]/', '', $rate_of_tax));

//             $salesTax   = $numericRate;
//             $valueExTax = round($lineTotal - $salesTax, 2);

//         } else {
//             // Fallback, assume percentage
//             $numericRate = floatval($rate_of_tax);

//             $valueExTax = round($lineTotal / (1 + ($numericRate / 100)), 2);
//             $salesTax   = round($lineTotal - $valueExTax, 2);
//         }

//         $hsCode = $product->hsCode;
//         $hs_desc = $product->hs_description;
//         $uom = $product->uom;
//         $sro_item = $product->sro_item;
//         $sale_type = $product->sale_type;
//         $sro_schedule = $product->sro_schedule;
        
//         $fbrItems[] = [
//             "hsCode" => $hsCode,
//             "productDescription" => $hs_desc,
//             "rate" => $rate_of_taxx,
//             "uoM" => $uom,
//             "quantity" => (float) number_format($quantity, 4, '.', ''),
//             "totalValues" => (float) number_format($lineTotal, 2, '.', ''),
//             "valueSalesExcludingST" => (float) number_format($valueExTax, 2, '.', ''),
//             "fixedNotifiedValueOrRetailPrice" => 0,
//             "salesTaxApplicable" => (float) number_format($salesTax, 2, '.', ''),
//             // "salesTaxApplicable" => 9,
//             // "salesTaxWithheldAtSource" => 5,
//             "salesTaxWithheldAtSource" => (float) number_format($cart->salesTaxWithheldAtSource, 2, '.', ''),
//             // numberic extra tax fbr 
//             "extraTax" => 0.00,
//             "furtherTax" => 0,
//             "sroScheduleNo" => $sro_schedule,
//             "fedPayable" => 0,
//             "discount" => (float) number_format($discount, 2, '.', ''),
//             // "discount" => $discount,
//             "saleType" => $sale_type,
//             // "saleType" => "Local Sale",
//             "sroItemSerialNo" => $sro_item,
//         ];
//     }

//     // BUILD FBR PAYLOAD
//     $customer = Customer::find($request->customer_id);
//      $businessId = session('business_id');
//      $businessData = Business::find($businessId);

//     $fbrPayload = [
//         "invoiceType" => "Sale Invoice",
//         "invoiceDate" => now()->format('Y-m-d'),
//         "sellerNTNCNIC" => $businessData->cnic,
//         "sellerBusinessName" => $businessData->business_name,
//         "sellerProvince" => $businessData->state,
//         "sellerAddress" => $businessData->address,
//         "buyerNTNCNIC" => "",
//         "buyerBusinessName" => $customer->name,
//         "buyerProvince" => $customer->province ?? "Punjab",
//         "buyerAddress" => $customer->address ?? "",
//         "buyerRegistrationType" => $customer->is_registered ? "Registered" : "Unregistered",
//         "invoiceRefNo" => "INV-" . $order->id,
//         "scenarioId" => "SN002",
//         "items" => $fbrItems
//     ];

//     // SEND TO FBR API
//     $token = env('FBR_TOKEN');

//    try {
//     $response = Http::withOptions([
//     'timeout' => 620,        // total request timeout
//     'connect_timeout' => 620, // connection timeout
//     ])->withHeaders([
//         'Authorization' => "Bearer $token",
//         'Content-Type' => 'application/json',
//         'Accept-Encoding' => 'gzip, deflate, br',
//         'Accept' => 'application/json'
//     ])->post("https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata_sb", $fbrPayload);

//     } catch (\Illuminate\Http\Client\ConnectionException $e) {
//         info('Connection Exception: ' . $e->getMessage());
// }

//     // RECORD PAYMENT (IF ANY)

//     if ($request->paid > 0) {
//         $order->transactions()->create([
//             'amount'      => $request->paid,
//             'customer_id' => $order->customer_id,
//             'user_id'     => auth()->id(),
//             'paid_by'     => 'cash',
//         ]);
//     }

//     PosCart::where('user_id', auth()->id())->delete();

//      $responseJson = $response->json();

//     $errorCode =
//     data_get($responseJson, 'validationResponse.invoiceStatuses.0.errorCode')
//     ?? data_get($responseJson, 'errorCode');

//     $errorMessage =
//     data_get($responseJson, 'validationResponse.invoiceStatuses.0.error')
//     ?? data_get($responseJson, 'error');

//     // update fbr api status and response in orders table
//     Order::where('id', $order->id)->update([            
//         'api_status' => $response->status(),
//         'error_code' => $errorCode,
//         'error_message' => $errorMessage,
//         'api_message' => $response->ok(),

//     ]);

//     // Save Order logs 
//        OrderLog::create([
//         'order_id'       => $order->id,
//         'request_payload'=> $fbrPayload,
//         'response'       => $responseJson,
//         'status_code'    => $response->status(),
//         'error_code'     => $errorCode,
//         'error_message'  => $errorMessage,
//         'success'        => $response->ok(),
//     ]);

//     // FBR Logs 

//     // Log the request & response
//     Log::channel('fbr')->info('FBR API Request', [
//         'order_id' => $order->id,
//         'payload'  => $fbrPayload
//     ]);


//     Log::channel('fbr')->info('FBR API Response', [
//         'order_id'       => $order->id,
//         'response'       => $responseJson,
//         'status_code'    => $response->status(),
//         'error_code'     => $errorCode,
//         'error_message'  => $errorMessage,
//         'success'        => $response->ok(),
//     ]);


//     // FINAL RESPONSE
//     return response()->json([
//         'message' => 'Order completed successfully!',
//         'fbr_payload_sent' => $fbrPayload,
//         'fbr_api_response' => $response->json(),
//         'fbr_api_response_statu' => $response->status(),
//         'fbr_api_response_message' => $response->ok(),
//     ], 200);


//     // return response()->json(['message' => 'Order completed successfully', 'order' => $order], 200);
// }


    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
    public function invoice($id)
    {
        $order = Order::with(['customer', 'products.product'])->findOrFail($id);
        $businessId = session('business_id');
        $business = Business::where('id', $businessId)->first();
       
        return view('backend.orders.print-invoice', compact('order', 'business'));
    }
    public function collection(Request $request, $id)
    {

        $order = Order::findOrFail($id);
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'amount' => 'required|numeric|min:1',
            ]);


            $due = $order->due - $data['amount'];
            $paid = $order->paid + $data['amount'];
            $order->due = round((float)$due, 2);
            $order->paid = round((float)$paid, 2);
            $order->status = round((float)$due, 2) <= 0;
            $order->save();
            $collection_amount = $data['amount'];
            //create order transaction

            $orderTransaction = $order->transactions()->create([
                'amount' => $data['amount'],
                'customer_id' => $order->customer_id,
                'user_id' => auth()->id(),
                'paid_by' => 'cash',
            ]);
            return to_route('backend.admin.collectionInvoice', $orderTransaction->id);
        }
        return view('backend.orders.collection.create', compact('order'));
    }

    //collection invoice by order_transaction id
    public function collectionInvoice($id)
    {
        $transaction = OrderTransaction::findOrFail($id);
        $collection_amount = $transaction->amount;
        $order = $transaction->order;
        return view('backend.orders.collection.invoice', compact('order', 'collection_amount', 'transaction'));
    }
    //transactions by order id
    public function transactions($id)
    {
        $order = Order::with('transactions')->findOrFail($id);
        return view('backend.orders.collection.index', compact('order'));
    }

    // public function posInvoice($id)
    // {
    //     $order = Order::with(['customer', 'products.product'])->findOrFail($id);
    //     $maxWidth = readConfig('receiptMaxwidth')??'300px';
    //     return view('backend.orders.pos-invoice', compact('order', 'maxWidth'));
    // }


    public function posInvoice($id)
    {
        $businessId = session('business_id');

        $order = Order::with(['customer', 'products.product'])
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $invoiceSettings = DB::table('invoice_settings')
            ->where('business_id', $businessId)
            ->first();

        $business = DB::table('businesses')->where('id', $businessId)->first();

        $maxWidth = $invoiceSettings->post_invoice_width ?? '300px';

        return view('backend.orders.pos-invoice', compact('order', 'maxWidth', 'invoiceSettings', 'business'));
    }


    public function fbrStatus($id) {
    $data = Order::find($id);

    if (!$data) {
        return response()->json([
            'message' => "Order not found"
        ]);
    }

    if ($data->api_status != 200) {
        $message = "API Not Run Due to Some Issue From FBR";
    } elseif (empty($data->error_code)) {
        $message = "Successfully Sent to FBR.";
    } else {
        $message = "Failed with Error Code: " . $data->error_code;
        if (!empty($data->error_message)) {
            $message .= " - " . $data->error_message;
        }
    }

    return response()->json([
        'message' => $message
    ]);
}

     
}