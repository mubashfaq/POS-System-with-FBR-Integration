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
use App\Services\FbrTokenService;

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
                ->addColumn('item', fn($data) => number_format($data->total_item, 2, '.', ','))
                ->addColumn('sub_total', fn($data) => number_format($data->sub_total, 2, '.', ','))
                ->addColumn('discount', fn($data) => number_format($data->discount, 2, '.', ','))
                ->addColumn('total', fn($data) => number_format($data->total, 2, '.', ','))
                ->addColumn('paid', fn($data) => number_format($data->paid, 2, '.', ','))
                ->addColumn('due', fn($data) => number_format($data->due, 2, '.', ','))
                ->addColumn('type', fn($data) => 
                    ($data->type == 'pos_invoice') 
                        ? '<span class="badge bg-info">POS</span>' 
                        : '<span class="badge bg-secondary">Digital</span>'
                )
                ->rawColumns(['type'])                // ->addColumn('stock_total', fn($data) => number_format($stockTotal->sum('grand_total'), 2, '.', ','))
                ->addColumn('status', fn($data) => $data->status
                    ? '<span class="badge bg-primary">Paid</span>'
                    : '<span class="badge bg-danger">Due</span>')
                ->addColumn('date', fn($data) => $data->created_at->format('Y-m-d'))
                ->addColumn('fbr_status', function ($d) {
                    if ($d->api_status == 200 && (empty($d->error_code) || $d->error_code == null) && $d->fbr_invoice_no != null ||$d->fbr_invoice_no != "") {
                        return "<button data-id=$d->id class='fbr-status btn btn-md bg-white' title='Data Submitted'><i class='fas fa-check-circle text-success'></i></button>";
                    }
                    if ($d->api_status == 200 && (!empty($d->error_code) || $d->error_code != null) && $d->fbr_invoice_no == null ||$d->fbr_invoice_no == "") {
                        return "<button data-id=$d->id class='fbr-status btn btn-md bg-white' title='Data Not Correct For FBR'><i class='fas fa-times-circle text-danger'></i></button>";
                    }
                    if ($d->api_status != 200 && $d->fbr_invoice_no == null ||$d->fbr_invoice_no != "") {
                        return "<button data-id=$d->id class='fbr-status btn btn-md bg-white' title='Api Faild' ><i class='fas fa-exclamation-triangle text-warning'></i></button>";
                    }
                })
                ->addColumn('action', function ($data) {
                    $buttons = '';

                    
                    if ($data->type == "pos_invoice") {
                        $buttons .= '<a class="btn btn-success btn-sm" href="' . route('backend.admin.orders.invoice', $data->id) . '"><i class="fas fa-file-invoice"></i> Invoice</a>';
                        $buttons .= '<a class="btn btn-secondary btn-sm" href="' . route('backend.admin.orders.pos-invoice', $data->id) . '"><i class="fas fa-file-invoice"></i> Pos Invoice</a>';
                    } else {
                        $buttons .= '<a class="btn btn-success btn-sm" href="' . route('backend.admin.orders.pos-receipt-digital', $data->id) . '"><i class="fas fa-file-invoice"></i> Receipt</a>';
                        $buttons .= '<a class="btn btn-secondary btn-sm" href="' . route('backend.admin.orders.pos-invoice-digital', $data->id) . '"><i class="fas fa-file-invoice"></i> Digital Invoice</a>';
                    }
                    if (!$data->status) {
                        $buttons .= '<a class="btn btn-warning btn-sm" href="' . route('backend.admin.due.collection', $data->id) . '"><i class="fas fa-receipt"></i> Due Collection</a>';
                    }
                    $buttons .= '<a class="btn btn-primary btn-sm" href="' . route('backend.admin.orders.transactions', $data->id) . '"><i class="fas fa-exchange-alt"></i> Transactions</a>';
                    return $buttons;
                })
                ->rawColumns(['saleId', 'customer', 'item', 'sub_total', 'discount', 'total', 'paid', 'due', 'type', 'date', 'status', 'fbr_status', 'action'])
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

        $userId =  auth()->id();

        $businessId = session('business_id');

        if (!$businessId) {
            return response()->json(['error' => 'No business selected'], 422);
        }

        $customer = Customer::find($request->customer_id);


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
            'user_id'     => $userId,
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

   // Sale TAX (NEW)
    $rateText = trim($product->pos_sale_tax);

    $saleTax = 0;

            if (str_ends_with($rateText, '%')) {
                $rate = floatval(str_replace('%', '', $rateText));
        $saleTax = round($lineTotal * ($rate / 100), 2);
    } else {
        $saleTax = floatval($rateText) ?: 0;
            }

    // âœ… FINAL TOTAL = line total + further tax
    $finalTotal = round($lineTotal + $saleTax, 2);

            $totalAmountOrder += $finalTotal;

            $order->products()->create([
                'quantity'       => $cart->quantity,
                'price'          => $unitPrice,
                'purchase_price' => $cart->purchaseItem ? $cart->purchaseItem->purchase_price : $product->purchase_price,
                'sub_total'      => $subTotal,
                'discount'       => $discount,
                'total'          => $finalTotal,
                'product_id'     => $product->id,
                'purchase_item_id' => $cart->purchase_item_id,
            ]);

            // Decrement product stock
            $product->quantity -= $cart->quantity;
            $product->save();

            // Decrement batch remaining_qty if batch is linked
            if ($cart->purchase_item_id && $cart->purchaseItem) {
                $cart->purchaseItem->decrement('remaining_qty', $cart->quantity);
            }
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

            $rateText = trim($product->pos_sale_tax); // 18%
            $taxRate  = (float) str_replace('%', '', $rateText); // 18
            $salesTax = 0;
            $valueExTax = $lineTotal; // already ex-tax
            if (str_ends_with($rateText, '%')) {
                $rate = floatval(str_replace('%', '', $rateText));
        
            $salesTax = round($valueExTax * ($rate / 100), 2);
            } else {
                $salesTax = floatval(preg_replace('/[^0-9.]/', '', $rateText));
            }



            $fbrItems[] = [

                "ItemCode" => $product->sku,
                "ItemName" => $product->name,
                "quantity" => $qty,
                "PCTCode" => $product->PCTCode,
                // "PCTCode" => "11001010",
                "TaxRate" => $taxRate,
                "SaleValue" => $valueExTax,
            "TotalAmount" => $lineTotal + $salesTax ,//+ $furtherTax,
                "TaxCharged" => $salesTax,
                "discount" => 0,
                "InvoiceType" => 1,

            ];
        }

        $customer = Customer::find($request->customer_id);
        $business = Business::find($businessId);

        $fbrPayload = [
            "POSID" => 819045,
            "USIN" => "USIN-" . $order->id,
            "DateTime" => now()->format('Y-m-d'),
            "buyerNTN" => $customer->ntn_cnic,
            "buyerCNIC" => $customer->ntn_cnic,
            "BuyerName" => $customer->name,
            "BuyerPhoneNumber" => $customer->phone,
            "items" => $fbrItems,
            "TotalBillAmount" => $lineTotal + $salesTax,
            "TotalQuantity" => $qty,
            "TotalSaleValue" => $valueExTax,
            "TotalTaxCharged" => $salesTax,
            "PaymentMode" => 2,
            "InvoiceType" => 1
        ];

        // dd($fbrPayload);

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




            $tokens = FbrTokenService::getToken();

            if (is_array($tokens)) {
                $token = $tokens['pos_token'];
            } else {
                $token = $tokens;
            }
            $tokenUrlEnd = FbrTokenService::getTokenUrl();
            $post_api_response = null;
            try {

                $post_api_response = Http::withOptions([
                    'timeout' => 620,
                    'connect_timeout' => 620,
                    'verify' => false,
                    //   CURLOPT_SSL_VERIFYHOST => false,
                    // CURLOPT_SSL_VERIFYPEER => false,
                ])->withHeaders([
                    'Authorization' => "Bearer $token",
                    'Content-Type' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Accept' => '*/*'
                ])->withBody(json_encode($fbrPayload), 'application/json')
                    ->post("https://esp.fbr.gov.pk:8244/FBR/v1/api/Live/PostData");
                //  ->post("https://esp.fbr.gov.pk:8244/FBR/v1/api/Live/PostData", $fbrPayload);
                // ])->post("https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb", $fbrPayload);


            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                info('Connection Exception: ' . $e->getMessage());
            }

            $postResponseJson = $post_api_response->json();
            // dd($postResponseJson);

            /* ================= FBR API CALL ================= */
            Log::info('FBR Payload', [
                'order_id' => $order->id ?? null,
                'payload'  => $fbrPayload,
                'post_response_pos' => $postResponseJson['Response'],
            ]);




            $fbr_invoice_number = data_get($postResponseJson, 'InvoiceNumber');


            if ($postResponseJson['Response'] == "Invoice received successfully") {
                DB::commit(); // save order data in db now 

                Order::where('id', $order->id)->update([
                    'fbr_invoice_no'    => $fbr_invoice_number,
                    'api_status'    => $post_api_response->status(),
                    'api_message'   => 1,
                    'type' => 'pos_invoice',
                ]);

                Log::channel('fbr')->info('FBR Payload', [
                    'order_id' => $order->id ?? null,
                    'user_id' => $userId,
                    'business_id' => $businessId,
                    'payload'  => $fbrPayload,
                    'post_response_pos' => $postResponseJson['Response'],
                ]);

                OrderLog::create([
                    'order_id'       => $order->id,
                    'user_id'        => $userId,
                    'business_id'    => $businessId,
                    'request_payload' => $fbrPayload,
                    'response'       => $postResponseJson['Response'],
                    'error_code'     => $postResponseJson['Code'] ?? null,
                    'error_message'  => $postResponseJson['Response'] ?? null,
                ]);
            } else {


                Log::channel('fbr')->info('FBR Payload', [
                    'order_id' => $order->id ?? null,
                    'user_id' => $userId,
                    'business_id' => $businessId,
                    'payload'  => $fbrPayload,
                    'post_response_pos' => $postResponseJson['Response'],
                ]);

                OrderLog::create([
                    'order_id'       => $order->id,
                    'request_payload' => $fbrPayload,
                    'response'       => $postResponseJson['Response'],
                    'error_code'     => $postResponseJson['Code'] ?? null,
                    'error_message'  => $postResponseJson['Response'] ?? null,
                ]);

                DB::rollBack();

                return response()->json([
                    'error' => $postResponseJson['Response'],
                    'error_code' => $postResponseJson->Code ?? null,
                    'error_message' => $postResponseJson['Response'] ?? null,
                    'fbr_api_response' => $postResponseJson,
                ]);
            }


            PosCart::where('user_id', auth()->id())->delete();

            return response()->json([
                'message' => 'Order completed successfully!',
                'fbr_payload_sent' => $fbrPayload,
                'fbr_api_response' => $postResponseJson,
                'order' => $order
            ], 200);
        }
    }


    public function storeDigital(Request $request)
    {
        $request->validate([
            'customer_id' => ['required', 'exists:customers,id', 'integer'],
            'order_discount' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
        ]);
        $userId = auth()->id();
        $businessId = session('business_id');

        if (!$businessId) {
            return response()->json(['error' => 'No business selected'], 422);
        }

        $customer = Customer::find($request->customer_id);


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
            'user_id'     => $userId,
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
            $subTotal  = round($unitPrice * $qty, 2);
            $discount  = round(($product->price - $unitPrice) * $qty, 2);

            // FURTHER TAX (NEW)
            $rateText = trim($product->rate_of_tax);
            $furtherTax = 0;

            if (str_ends_with($rateText, '%')) {
                $rate = floatval(str_replace('%', '', $rateText));
                //  $valueExTax = round($lineTotal / (1 + ($rate / 100)), 2);
                $valueExTax = round($lineTotal, 2);
                $salesTax  = round($valueExTax * ($rate / 100), 2);

                if ($customer->is_registered && strtolower($customer->is_registered) === 'unregistered' && $product->sale_type != '3rd Schedule Goods') {
                    $furtherTax = round($valueExTax * (4 / 100), 2);
                } else {
                    $furtherTax = 0;
                }
            } else {
                $salesTax = floatval(preg_replace('/[^0-9.]/', '', $rateText));
                // $valueExTax = round($lineTotal - $salesTax, 2);
                $valueExTax = round($lineTotal, 2);

                if ($customer->is_registered && strtolower($customer->is_registered) === 'unregistered' && $product->sale_type != '3rd Schedule Goods') {
                    $furtherTax = round($valueExTax * (4 / 100), 2);
                } else {
                    $furtherTax = 0;
                }
            }

            //  FINAL TOTAL = line total + further tax
            $finalTotal = round($lineTotal + $salesTax + $furtherTax, 2);

            $totalAmountOrder += $finalTotal;

            $order->products()->create([
                'quantity'       => $cart->quantity,
                'price'          => $unitPrice,
                'purchase_price' => $cart->purchaseItem ? $cart->purchaseItem->purchase_price : $product->purchase_price,
                'sub_total'      => $subTotal,
                'discount'       => $discount,
                'total'          => $finalTotal,
                'product_id'     => $product->id,
                'purchase_item_id' => $cart->purchase_item_id,
            ]);

            // Decrement product stock
            $product->quantity -= $cart->quantity;
            $product->save();

            // Decrement batch remaining_qty if batch is linked
            if ($cart->purchase_item_id && $cart->purchaseItem) {
                $cart->purchaseItem->decrement('remaining_qty', $cart->quantity);
            }
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
                //  $valueExTax = round($lineTotal / (1 + ($rate / 100)), 2);
                $valueExTax = $lineTotal;
                $salesTax  = round($valueExTax * ($rate / 100), 2);

                if ($customer->is_registered && strtolower($customer->is_registered) === 'unregistered' && $product->sale_type != '3rd Schedule Goods') {
                    $furtherTax = round($valueExTax * (4 / 100), 2);
                } else {
                    $furtherTax = 0;
                }
            } else {
                $salesTax = floatval(preg_replace('/[^0-9.]/', '', $rateText));
                // $valueExTax = round($lineTotal - $salesTax, 2);
                $valueExTax = $lineTotal;

                if ($customer->is_registered && strtolower($customer->is_registered) === 'unregistered' && $product->sale_type != '3rd Schedule Goods') {
                    $furtherTax = round($valueExTax * (4 / 100), 2);
                } else {
                    $furtherTax = 0;
                }
            }

            // $fbrItems[] = [
            //     "hsCode" => $product->hsCode,
            //     "productDescription" => $product->name,
            //     // "productDescription" => $product->hs_description,
            //     "rate" => $rateText,
            //     "uoM" => $product->uom,
            //     "quantity" => $qty,
            //     "totalValues" => $lineTotal + $furtherTax,
            //     "valueSalesExcludingST" => $valueExTax,
            //     "fixedNotifiedValueOrRetailPrice" => $product->price,
            //     "salesTaxApplicable" => $salesTax,
            //     "salesTaxWithheldAtSource" => 0,
            //     "extraTax" => 0,
            //     "furtherTax" => (float) $furtherTax,
            //     "sroScheduleNo" => $product->sro_schedule ?: null,
            //     "fedPayable" => 0,
            //     "discount" => 0,
            //     "saleType" => $product->sale_type,
            //     "sroItemSerialNo" => $product->sro_item
            // ];

            $fbrItems[] = [
                "hsCode" => $product->hsCode,
                "productDescription" => $product->name,
                "rate" => $rateText,
                "uoM" => $product->uom,

                // FLOAT banane ke liye (1.0, 50.0)
                "quantity" => number_format((float) $qty, 2, '.', ''),
                "totalValues" => number_format(($lineTotal + $salesTax + $furtherTax), 2, '.', ''),
                "valueSalesExcludingST" => number_format((float) $valueExTax, 2, '.', ''),
                "fixedNotifiedValueOrRetailPrice" => 0,
                "salesTaxApplicable" => number_format((float) $salesTax, 2, '.', ''),

                "salesTaxWithheldAtSource" => 0,
                "extraTax" => 0,
                "furtherTax" => number_format((float) $furtherTax, 2, '.', ''),

                // NULL handling
                "sroScheduleNo" => $product->sro_schedule ?: null,
                "fedPayable" => 0,
                "discount" => 0,
                "saleType" => $product->sale_type,
                "sroItemSerialNo" => $product->sro_item ?: null
            ];
        }

        $customer = Customer::find($request->customer_id);
        $business = Business::find($businessId);

        $buyerRegType = null;

        if (strtolower($customer->is_registered) === 'registered') {
            $buyerRegType = 'Registered';
            $SN = "SN001";
        } else {
            $buyerRegType = 'Unregistered';
            $SN = "SN002";
        }
        // $fbrPayload = [
        //     "invoiceType" => "Sale Invoice",
        //     "invoiceDate" => now()->format('Y-m-d'),
        //     "sellerNTNCNIC" => $business->cnic,
        //     "sellerBusinessName" => $business->business_name,
        //     "sellerProvince" => $business->state,
        //     "sellerAddress" => $business->address,
        //     "buyerNTNCNIC" => $customer->ntn_cnic,
        //     "buyerBusinessName" => $customer->name,
        //     "buyerProvince" => $customer->province,
        //     "buyerAddress" => $customer->address,
        //     "buyerRegistrationType" => $buyerRegType,
        //     "invoiceRefNo" => "INV-" . $order->id,
        //     // "invoiceRefNo" => "INV-" . "INV00112200",
        //     "scenarioId" => "SN001",
        //     "items" => $fbrItems
        // ];


        $fbrPayload = [
            "buyerRegistrationType" => $buyerRegType,
            "invoiceType" => "Sale Invoice",
            // "invoiceDate" => now()->format('Y-m-d'),
            // "invoiceDate" => now()->subDay()->format('Y-m-d'),
            "invoiceDate" => now('UTC')->format('Y-m-d'),
            "sellerNTNCNIC" => $business->ntn,
            "sellerBusinessName" => $business->business_name,
            "sellerProvince" => strtolower($business->state), // punjab
            "sellerAddress" => $business->address ?: null,
            "buyerNTNCNIC" => $customer->ntn_cnic,
            "buyerBusinessName" => $customer->name,
            "buyerProvince" => $customer->province,
            "buyerAddress" => $customer->address,
            "invoiceRefNo" => "INV-" . $order->id,
            "scenarioId" => $SN,
            "items" => $fbrItems
        ];
            

        // Check if FBR integration is disabled
        if (!FbrTokenService::isIntegrationEnabled()) {

            DB::commit();

            PosCart::where('user_id', auth()->id())->delete();

            Order::where('id', $order->id)->update([
                'type' => 'digital_invoice',
            ]);


            return response()->json([
                'message' => 'Order completed successfully (FBR Not Integrated)',
                'order'   => $order,
            ], 200);
        } else {



            // $token = env('FBR_TOKEN');
            $tokens = FbrTokenService::getToken();
            if (is_array($tokens)) {
                $token = $tokens['digital_token'];
            } else {
                $token = $tokens;
            }

            $tokenUrlEnd = FbrTokenService::getTokenUrl();


            try {

                $response = Http::withOptions([
                    'timeout' => 2420,
                    'connect_timeout' => 2420,
                ])->withHeaders([
                    'Authorization' => "Bearer $token",
                    'Content-Type' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Accept' => '*/*'
                ])->withBody(json_encode($fbrPayload), 'application/json')
                    ->post("https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata$tokenUrlEnd");

                // ->post("https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata_sb", $fbrPayload);

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                info('Connection Exception: ' . $e->getMessage());
            }
            $body = $response->body();

            // fix FBR invalid JSON (trailing comma)
            $cleanBody = preg_replace('/,\s*([}\]])/', '$1', $body);

            $responseJson = json_decode($cleanBody, true);


            /* ================= FBR API CALL ================= */
            Log::info('FBR Payload', [
                'order_id' => $order->id ?? null,
                'payload'  => $fbrPayload,
                'validation_response_digital' => $responseJson,
            ]);

            Log::channel('fbr')->info('FBR Payload', [
                'order_id' => $order->id ?? null,
                'user_id' => $userId,
                'business_id' => $businessId,
                'payload'  => $fbrPayload,
                'validation_response_digital' => $responseJson,
            ]);

            OrderLog::create([
                'order_id'       => $order->id,
                'user_id'        => $userId,
                'business_id'    => $businessId,
                'request_payload' => $fbrPayload,
                'response'       => $responseJson,
                'status_code'    => $response->status(),
                'success'        => $response->ok(),
            ]);


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
                    'timeout' => 2420,
                    'connect_timeout' => 2420,

                ])->withHeaders([
                    'Authorization' => "Bearer $token",
                    'Content-Type' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Accept' => '*/*'
                ])->withBody(json_encode($fbrPayload), 'application/json')
                    ->post("https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata$tokenUrlEnd");
                //  ->post("https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb", $fbrPayload);


            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                info('Connection Exception: ' . $e->getMessage());
            }

            $postResponseJson = $post_api_response->json();
            $fbr_invoice_number = data_get($postResponseJson, 'invoiceNumber');

            Order::where('id', $order->id)->update([
                'type' => 'digital_invoice',
            ]);


            Log::info('FBR Payload', [
                'order_id' => $order->id ?? null,
                'post_response_digital' => $postResponseJson,
            ]);

            Log::channel('fbr')->info('FBR Payload', [
                'order_id' => $order->id ?? null,
                'user_id' => $userId,
                'business_id' => $businessId,
                'post_response_digital' => $postResponseJson,
                'fbr_invoice_number' => $fbr_invoice_number,
            ]);

            OrderLog::create([
                'order_id'       => $order->id,
                'user_id'        => $userId,
                'business_id'    => $businessId,
                'request_payload' => "POST API Response",
                'response'       => $postResponseJson,
                'status_code'    => $response->status(),
                'error_code'     => $errorCode,
                'error_message'  => $errorMessage,
                'success'        => $response->ok(),
                'fbr_invoice_number' => $fbr_invoice_number,
            ]);


            /* ================= SAVE API STATUS & LOGS (NO ROLLBACK) ================= */
            Order::where('id', $order->id)->update([
                'fbr_invoice_no'    => $fbr_invoice_number,
            ]);



            PosCart::where('user_id', auth()->id())->delete();

            return response()->json([
                'message' => 'Order completed successfully!',
                'fbr_payload_sent' => $fbrPayload,
                'fbr_api_response' => $responseJson,
                'order' => $order
            ], 200);
        }
    }
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
        $fbrIntegration = DB::table('fbr_tokens')->where('business_id', $businessId)->value('fbr_integration');

        return view('backend.orders.print-invoice', compact('order', 'business', 'fbrIntegration'));
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

        $fbrIntegration = DB::table('fbr_tokens')->where('business_id', $businessId)->value('fbr_integration');

        return view('backend.orders.pos-invoice', compact('order', 'maxWidth', 'invoiceSettings', 'business', 'fbrIntegration'));
    }


    public function posInvoiceDigital($id)
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

        $fbrIntegration = DB::table('fbr_tokens')->where('business_id', $businessId)->value('fbr_integration');

        return view('backend.orders.pos-invoice-digital', compact('order', 'maxWidth', 'invoiceSettings', 'business', 'fbrIntegration'));
        // return view('backend.orders.pos-invoice-digital', compact('order', 'maxWidth', 'invoiceSettings', 'business'));
        // return view('backend.orders.print-invoice', compact('order', 'maxWidth', 'invoiceSettings', 'business' , 'fbrIntegration'));
    }

    public function fbrStatus($id)
    {
        $data = Order::find($id);

        if (!$data) {
            return response()->json([
                'message' => "Order not found"
            ]);
        }

        if ($data->api_status != 200 ) {
            $message = "API Not Run Due to Some Issue From FBR";
        } elseif ($data->api_status == 200 && empty($data->error_code) && ($data->fbr_invoice_no != null )) {
            $message = "Successfully Sent to FBR.";
        } else {
            $message = "Failed to send to FBR " . $data->error_code;
            if (!empty($data->error_message)) {
                $message .= " - " . $data->error_message;
            }
        }

        return response()->json([
            'message' => $message
        ]);
    }
}