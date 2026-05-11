<?php

namespace App\Http\Controllers\Api\Pos;

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
        $businessId = $request->attributes->get('business')->id;
    
        if (!$businessId) {
        return response()->json([
            'status' => false,
            'message' => 'No business selected'
        ], 422);
        }

        $perPage = $request->get('per_page', 10);
    
        $orders = Order::with('customer')
            ->where('business_id', $businessId)
            ->latest()
            ->paginate($perPage); // important for mobile

            $fbrIntegration = DB::table('fbr_tokens')
            ->where('business_id', $businessId)
            ->value('fbr_integration');
    
        $data = $orders->getCollection()->map(function ($order) use ($fbrIntegration) {
            return [
                'id' => $order->id,
                'sale_id' => "#" . $order->id,
                'customer' => $order->customer->name ?? '-',
                'items' => $order->total_item,
                'sub_total' => (float) $order->sub_total,
                'discount' => (float) $order->discount,
                'total' => (float) $order->total,
                'paid' => (float) $order->paid,
                'due' => (float) $order->due,
    
                'type' => $order->type,
                'type_label' => $order->type == 'pos_invoice' ? 'POS' : 'Digital',
    
                'payment_status' => $order->status ? 'paid' : 'due',
    
                'fbr_status' => $this->getFbrStatus($order, $fbrIntegration),
    
                'date' => $order->created_at->format('Y-m-d'),
            ];
        });
    
        return response()->json([
            'status' => true,
            'message' => 'Orders fetched successfully',
            'data' => $data,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        ]);
    }

    private function getFbrStatus($order, $integration)
    {
        if (!$integration) {
            return 'not_integrated';
        }

        // 🚫 Disabled
        if ($integration == 'disabled') {
            return 'disabled';
        }

        // ✅ Submitted
        if ($order->api_status == 200 && empty($order->error_code)) {
            return 'submitted';
        }

        // ❌ Invalid
        if ($order->api_status == 200 && !empty($order->error_code)) {
            return 'invalid_data';
        }

        // ⚠️ Failed
        return 'failed';
    }

// <<<<<<<<<<<<<<<<<<<<< first sennd fbi ten save in  >>>>>>>>>>>>>>>>>>>>>

public function store(Request $request)
{
    $request->validate([
        'customer_id'    => ['required', 'exists:customers,id', 'integer'],
        'order_discount' => ['nullable', 'numeric', 'min:0'],
        'paid'           => ['nullable', 'numeric', 'min:0'],
    
        // NEW
        'items'                       => ['required', 'array', 'min:1'],
        'items.*.product_id'          => ['required', 'exists:products,id', 'integer'],
        'items.*.quantity'            => ['required', 'numeric', 'min:0.01'],
        'items.*.purchase_item_id'    => ['nullable', 'exists:purchase_items,id', 'integer'],
        'items.*.price_override'      => ['nullable', 'numeric', 'min:0'],
    ]);

    $businessId = $request->attributes->get('business')->id;

    if (!$businessId) {
        return response()->json(['error' => 'No business selected'], 422);
    }

    $customer = Customer::find($request->customer_id);


    //  Request items ko cart jaisi collection mein convert karo
    $itemsInput = collect($request->items);

    $productIds = $itemsInput->pluck('product_id')->toArray();
    $purchaseItemIds = $itemsInput->pluck('purchase_item_id')->filter()->toArray();

    // Eager load - N+1 avoid karne ke liye
    $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
    $purchaseItems = PurchaseItem::whereIn('id', $purchaseItemIds)->get()->keyBy('id');

    // Cart jaisi collection banao
    $carts = $itemsInput->map(function ($item) use ($products, $purchaseItems) {
        $obj = new \stdClass();
        $obj->product         = $products[$item['product_id']] ?? null;
        $obj->quantity        = $item['quantity'];
        $obj->purchase_item_id = $item['purchase_item_id'] ?? null;
        $obj->purchaseItem    = isset($item['purchase_item_id']) 
                                ? ($purchaseItems[$item['purchase_item_id']] ?? null) 
                                : null;
        $obj->price_override  = $item['price_override'] ?? null;
        return $obj;
    });

    // Validate products exist
    if ($carts->contains(fn($c) => is_null($c->product))) {
        return response()->json(['error' => 'One or more products not found'], 422);
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
    $totalTaxOrder = 0;
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
            $ratePerUnit = floatval($rateText) ?: 0;
            $saleTax = round($ratePerUnit * $qty, 2);
        }

        $finalTotal = round($lineTotal + $saleTax, 2);

        $totalAmountOrder += $finalTotal;
        $totalTaxOrder += $saleTax;

        $order->products()->create([
            'quantity'       => $cart->quantity,
            'price'          => $unitPrice,
            'purchase_price' => $cart->purchaseItem ? $cart->purchaseItem->purchase_price : $product->purchase_price,
            'sub_total'      => $subTotal,
            'discount'       => $discount,
            'tax_rate'       => $rateText,
            'sale_tax'       => $saleTax,
            'further_tax'    => 0,
            'total'          => $finalTotal,
            'product_id'     => $product->id,
            'purchase_item_id' => $cart->purchase_item_id,
        ]);

        // ── BATCH: frontend ne purchase_item_id diya ──
        if ($cart->purchase_item_id && $cart->purchaseItem) {
            $cart->purchaseItem->decrement('remaining_qty', $qty);

        // ── FIFO: purchase_item_id null hai, oldest batches se consume karo ──
        } else {
            $qtyToConsume = $qty; // ✅ alag variable FIFO ke liye
        
            $purchaseItems = PurchaseItem::where('product_id', $product->id)
                ->where('remaining_qty', '>', 0)
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();
        
            foreach ($purchaseItems as $pi) {
                if ($qtyToConsume <= 0) break;
        
                $consume = min($pi->remaining_qty, $qtyToConsume);
                $pi->decrement('remaining_qty', $consume);
                $qtyToConsume -= $consume;
            }
        
            if ($qtyToConsume > 0) {
                DB::rollBack();
                return response()->json([
                    'error' => "Insufficient stock for product: {$product->name}"
                ], 422);
            }
        }

        // Product total qty decrement (dono cases mein)
        $product->decrement('quantity', $qty);
        $product->save();
    }

    $total = $totalAmountOrder - $orderDiscount;
    $due   = $total - ($request->paid ?? 0);

    $order->update([
        'sub_total' => $totalAmountOrder,
        'discount'  => $orderDiscount,
        'total_sale_tax' => round($totalTaxOrder, 2),
        'total_further_tax' => 0,
        'paid'      => $request->paid,
        'total'     => round($total, 2),
        'due'       => round($due, 2),
        'status'    => round($due, 2) <= 0,
    ]);

    /* ================= FBR ITEMS ================= */
    $fbrItems = [];
    $lineTotal = 0;
    foreach ($carts as $cart) {
        $product = $cart->product;
        

        $qty = round($cart->quantity, 2);
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
            $ratePerUnit = floatval(preg_replace('/[^0-9.]/', '', $rateText));
            $salesTax = round($ratePerUnit * $qty, 2);
        }


       
        $fbrItems[] = [

            "ItemCode" => $product->sku,
            "ItemName" => $product->name,
            "quantity" => $qty,
            "PCTCode" => $product->PCTCode,
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
        "TotalBillAmount" => $lineTotal + $salesTax  ,
        "TotalQuantity" => $qty,
        "TotalSaleValue" => $valueExTax,
        "TotalTaxCharged" => $salesTax,
        "PaymentMode" => 2,
        "InvoiceType" => 1
    ];
    
    if (!FbrTokenService::isIntegrationEnabled()) {

        DB::commit();

        Order::where('id', $order->id)->update([
            'type' => 'pos_invoice',
        ]);

        return response()->json([
            'message' => 'Order completed successfully (FBR Not Integrated)',
            'order'   => $order,
        ], 200);
    } else {

    /* ================= FBR API CALL ================= */
    Log::info('FBR Payload', [
    'order_id' => $order->id ?? null,
    'payload'  => $fbrPayload
    ]);

    
    $tokens = FbrTokenService::getToken();
    if (is_array($tokens)) {
        $token = $tokens['pos_token'];
    } else {
        $token = $tokens;
    }
    
    $post_api_response=null;
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
                'Accept' => 'application/json'
             ])->withBody(json_encode($fbrPayload), 'application/json')
                ->post("https://esp.fbr.gov.pk:8244/FBR/v1/api/Live/PostData");
            //  ->post("https://esp.fbr.gov.pk:8244/FBR/v1/api/Live/PostData", $fbrPayload);
    //         ])->post("https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb", $fbrPayload);
    

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // info('Connection Exception: ' . $e->getMessage());
            dd("im here"); 
        }


        $postResponseJson = $post_api_response->json();
         
        
        $fbr_invoice_number = data_get($postResponseJson, 'InvoiceNumber');


        if($postResponseJson['Response'] == "Invoice received successfully"){
            DB::commit();

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
        'customer_id'    => ['required', 'exists:customers,id', 'integer'],
        'order_discount' => ['nullable', 'numeric', 'min:0'],
        'paid'           => ['nullable', 'numeric', 'min:0'],
    
        // NEW
        'items'                       => ['required', 'array', 'min:1'],
        'items.*.product_id'          => ['required', 'exists:products,id', 'integer'],
        'items.*.quantity'            => ['required', 'numeric', 'min:0.01'],
        'items.*.purchase_item_id'    => ['nullable', 'exists:purchase_items,id', 'integer'],
        'items.*.price_override'      => ['nullable', 'numeric', 'min:0'],
    ]);

    $businessId = $request->attributes->get('business')->id;

    if (!$businessId) {
        return response()->json(['error' => 'No business selected'], 422);
    }

    $customer = Customer::find($request->customer_id);


    //  Request items ko cart jaisi collection mein convert karo
    $itemsInput = collect($request->items);

    $productIds = $itemsInput->pluck('product_id')->toArray();
    $purchaseItemIds = $itemsInput->pluck('purchase_item_id')->filter()->toArray();

    // Eager load - N+1 avoid karne ke liye
    $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
    $purchaseItems = PurchaseItem::whereIn('id', $purchaseItemIds)->get()->keyBy('id');

    // Cart jaisi collection banao
    $carts = $itemsInput->map(function ($item) use ($products, $purchaseItems) {
        $obj = new \stdClass();
        $obj->product         = $products[$item['product_id']] ?? null;
        $obj->quantity        = $item['quantity'];
        $obj->purchase_item_id = $item['purchase_item_id'] ?? null;
        $obj->purchaseItem    = isset($item['purchase_item_id']) 
                                ? ($purchaseItems[$item['purchase_item_id']] ?? null) 
                                : null;
        $obj->price_override  = $item['price_override'] ?? null;
        return $obj;
    });

    // Validate products exist
    if ($carts->contains(fn($c) => is_null($c->product))) {
        return response()->json(['error' => 'One or more products not found'], 422);
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
    $totalSaleTax = 0;
    $totalFurtherTax = 0;
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
            $valueExTax = round($lineTotal, 2);
            $salesTax  = round($valueExTax * ($rate / 100), 2);

            if ($customer->is_registered && strtolower($customer->is_registered) === 'unregistered' && $product->sale_type != '3rd Schedule Goods') {
                $furtherTax = round($valueExTax * (4 / 100), 2);
            } else {
                $furtherTax = 0;
            }
        } else {
            $ratePerUnit = floatval(preg_replace('/[^0-9.]/', '', $rateText));
            $salesTax = round($ratePerUnit * $qty, 2);
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
        $totalSaleTax += $salesTax;
        $totalFurtherTax += $furtherTax;
        $order->products()->create([
            'quantity'       => $cart->quantity,
            'price'          => $unitPrice,
            'purchase_price' => $cart->purchaseItem ? $cart->purchaseItem->purchase_price : $product->purchase_price,
            'sub_total'      => $subTotal,
            'discount'       => $discount,
            'tax_rate'       => $rateText,
            'sale_tax'       => $salesTax,
            'further_tax'    => $furtherTax,
            'total'          => $finalTotal,
            'product_id'     => $product->id,
            'purchase_item_id' => $cart->purchase_item_id,
        ]);

        // ── BATCH: frontend ne purchase_item_id diya ──
        if ($cart->purchase_item_id && $cart->purchaseItem) {
            $cart->purchaseItem->decrement('remaining_qty', $qty);

        // ── FIFO: purchase_item_id null hai, oldest batches se consume karo ──
        } else {
            $qtyToConsume = $qty; // ✅ alag variable FIFO ke liye
        
            $purchaseItems = PurchaseItem::where('product_id', $product->id)
                ->where('remaining_qty', '>', 0)
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();
        
            foreach ($purchaseItems as $pi) {
                if ($qtyToConsume <= 0) break;
        
                $consume = min($pi->remaining_qty, $qtyToConsume);
                $pi->decrement('remaining_qty', $consume);
                $qtyToConsume -= $consume;
            }
        
            if ($qtyToConsume > 0) {
                DB::rollBack();
                return response()->json([
                    'error' => "Insufficient stock for product: {$product->name}"
                ], 422);
            }
        }

        // Product total qty decrement (dono cases mein)
        $product->decrement('quantity', $qty);
        $product->save();
    }

    $total = $totalAmountOrder - $orderDiscount;
    $due   = $total - ($request->paid ?? 0);

    $order->update([
        'sub_total' => $totalAmountOrder,
        'discount'  => $orderDiscount,
        'total_sale_tax' => round($totalSaleTax, 2),
        'total_further_tax' => round($totalFurtherTax, 2),
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
        $unitPrice = $cart->price_override ?? $product->discounted_price;
        $lineTotal = round($unitPrice * $qty, 2);

        $rateText = trim($product->rate_of_tax);

        if (str_ends_with($rateText, '%')) {
            $rate = floatval(str_replace('%', '', $rateText));
            $valueExTax = $lineTotal;
            $salesTax  = round($valueExTax * ($rate / 100), 2);

            if($customer->is_registered && strtolower($customer->is_registered) === 'unregistered' && $product->sale_type != '3rd Schedule Goods'){
                $furtherTax = round($valueExTax * (4 / 100), 2);
  
            }else{
                $furtherTax = 0;
            }

        } else {
            $ratePerUnit = floatval(preg_replace('/[^0-9.]/', '', $rateText));
            $salesTax = round($ratePerUnit * $qty, 2);
           $valueExTax = $lineTotal;

             if($customer->is_registered && strtolower($customer->is_registered) === 'unregistered' && $product->sale_type != '3rd Schedule Goods'){
                $furtherTax = round($valueExTax * (4 / 100), 2);
  
            }else{
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
            // "valueSalesExcludingST" => number_format((float) $valueExTax , 2),
            "valueSalesExcludingST" => number_format((float) $valueExTax, 2, '.', ''),
            "fixedNotifiedValueOrRetailPrice" => number_format((float) $product->price,2, '.', ''),
            "salesTaxApplicable" => number_format((float) $salesTax,2, '.', ''),

            "salesTaxWithheldAtSource" => 0,
            "extraTax" => 0,
            "furtherTax" => number_format((float) $furtherTax,2, '.', ''),

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
    Log::info('FBR Payload', [
        'order_id' => $order->id ?? null,
        'payload'  => $fbrPayload
    ]);
    
    // dd($fbrPayload);
    
    // Check if FBR integration is disabled
    if (!FbrTokenService::isIntegrationEnabled()) {

        DB::commit();

        return response()->json([
            'message' => 'Order completed successfully (FBR Not Integrated)',
            'order'   => $order,
        ], 200);
    } else {

    /* ================= FBR API CALL ================= */
    Log::info('FBR Payload', [
    'order_id' => $order->id ?? null,
    'payload'  => $fbrPayload
]);


// dd(json_encode($fbrPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
// dd($fbrPayload);

    $tokens = FbrTokenService::getToken();
    if (is_array($tokens)) {
        $token = $tokens['digital_token'];
    } else {
        $token = $tokens;
    }
    
    try {
        // $response = Http::withOptions([
        //     'timeout' => 2420,
        //     'connect_timeout' => 2420,
        // ])->withHeaders([
        //     'Authorization' => "Bearer $token",
        //     'Content-Type' => 'application/json',        
        //     'Accept-Encoding' => 'gzip, deflate, br',
        //     'Accept' => '*/*'
        // ])->post("https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata_sb", $fbrPayload);
        $response = Http::withOptions([
            'timeout' => 2420,
            'connect_timeout' => 2420,
        ])->withHeaders([
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept' => '*/*'
        ])->withBody(json_encode($fbrPayload), 'application/json')
        ->post('https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata');

        // ->post("https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata_sb", $fbrPayload);
       
    } catch (\Illuminate\Http\Client\ConnectionException $e) {
        info('Connection Exception: ' . $e->getMessage());
    }
    $body = $response->body();
   
    // fix FBR invalid JSON (trailing comma)
    $cleanBody = preg_replace('/,\s*([}\]])/', '$1', $body);

    $responseJson = json_decode($cleanBody, true);

    

    
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
            ->post("https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata");
        //  ->post("https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb", $fbrPayload);
    
              
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            info('Connection Exception: ' . $e->getMessage());
        }

        $postResponseJson = $post_api_response->json();

        
        $fbr_invoice_number = data_get($postResponseJson, 'invoiceNumber');
        
            /* ================= SAVE API STATUS & LOGS (NO ROLLBACK) ================= */
            Order::where('id', $order->id)->update([
                'fbr_invoice_no'    => $fbr_invoice_number,
            ]);

    

    // PosCart::where('user_id', auth()->id())->delete();
    
    return response()->json([
        'message' => 'Order completed successfully!',
        'fbr_payload_sent' => $fbrPayload,
        'fbr_api_response' => $responseJson,
        'order' => $order
    ], 200); 
    }
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
        
        //         return response()->json([
        //             'message' => 'Order completed successfully (FBR Not Integrated)',
        //             'order'   => $order,
        //         ], 200);
        //     } else {

        //         $tokens = FbrTokenService::getToken();

        //         if (is_array($tokens)) {
        //             $token = $tokens['digital_token'];
        //         } else {
        //             $token = $tokens;
        //         }

        //         try {
        //             $response = Http::withOptions([
        //                 'timeout' => 620,
        //                 'connect_timeout' => 620,
        //             ])->withHeaders([
        //                 'Authorization' => "Bearer $token",
        //                 'Content-Type' => 'application/json',
        //                 'Accept-Encoding' => 'gzip, deflate, br',
        //                 'Accept' => 'application/json'
        //             ])->post("https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata_sb", $fbrPayload);
        //         } catch (\Illuminate\Http\Client\ConnectionException $e) {
        //             info('Connection Exception: ' . $e->getMessage());
        //         }

        //         $responseJson = $response->json();

        //         $statusCode = data_get($responseJson, 'validationResponse.statusCode');
        //         $errorCode  = data_get($responseJson, 'validationResponse.errorCode');
        //         $errorMsg   = data_get($responseJson, 'validationResponse.error');

        //         $order->update([
        //             'api_status'    => $response->status(),
        //             'error_code'    => $errorCode,
        //             'error_message' => $errorMsg,
        //             'api_message'   => $response->ok(),
        //         ]);

        //         if ($statusCode != "00") {

        //             DB::rollBack();

        //             return response()->json([
        //                 'error' => 'FBR validation failed',
        //                 'error_code' => $errorCode,
        //                 'error_message' => $errorMsg,
        //                 'fbr_response' => $responseJson
        //             ], 422);
        //         }

        //         DB::commit();

        //         /* ================= POST INVOICE ================= */

        //         try {
        //             $post_api_response = Http::withOptions([
        //                 'timeout' => 620,
        //                 'connect_timeout' => 620,

        //             ])->withHeaders([
        //                 'Authorization' => "Bearer $token",
        //                 'Content-Type' => 'application/json',
        //                 'Accept-Encoding' => 'gzip, deflate, br',
        //                 'Accept' => 'application/json'
        //             ])->post("https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb", $fbrPayload);
        //         } catch (\Illuminate\Http\Client\ConnectionException $e) {
        //             info('Connection Exception: ' . $e->getMessage());
        //         }

        //         $postResponseJson = $post_api_response->json();

        //         $invoiceNumber = data_get($postResponseJson, 'invoiceNumber');

        //         $order->update([
        //             'fbr_invoice_no' => $invoiceNumber,
        //         ]);

        //         PosCart::where('user_id', auth()->id())->delete();

        //         return response()->json([
        //             'message' => 'Order completed successfully',
        //             'order'   => $order,
        //             'fbr_invoice_no' => $invoiceNumber
        //         ], 200);
        //     }
        // } catch (\Exception $e) {

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

        return view('backend.orders.pos-invoice-digital', compact('order', 'maxWidth', 'invoiceSettings', 'business'));
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