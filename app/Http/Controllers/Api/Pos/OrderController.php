<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Jobs\SendFBRInvoiceJob;
use App\Models\Business;
use App\Models\BusinessCounter;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\FbrTokenService;
use App\Services\DuplicateOrderService;
use App\Exceptions\{FbrRejectedException, InsufficientStockException};
use App\Services\Pos\PosOrderService;

class OrderController extends Controller
{
    protected $duplicateOrderService;

    public function __construct(DuplicateOrderService $duplicateOrderService, private PosOrderService $orders)
    {
        $this->duplicateOrderService = $duplicateOrderService;
    }
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

        $orders = Order::with([
            'customer:id,name,address,ntn_cnic,phone,is_registered',

            'products' => function ($query) {
                $query->where('is_accessory', 0)
                    ->select(
                        'id',
                        'order_id',
                        'product_id',
                        'quantity',
                        'price',
                        'total',
                        'sale_tax',
                        'further_tax'
                    );
            },

            'products.product:id,name'
        ])
            ->where('business_id', $businessId)
            ->latest()
            ->paginate($perPage);

        $fbrIntegration = DB::table('fbr_tokens')
            ->where('business_id', $businessId)
            ->value('fbr_integration');

        $data = $orders->getCollection()->map(function ($order) use ($fbrIntegration) {
            return [
                'id' => $order->id,
                'sale_id' => "#" . $order->id,
                'customer' => $order->customer ? [
                    'name' => $order->customer->name,
                    'address' => $order->customer->address,
                    'ntn_cnic' => $order->customer->ntn_cnic,
                    'phone' => $order->customer->phone,
                    'is_registered' => $order->customer->is_registered,
                ] : null,
                'items_count' => $order->total_item,
                'items' => $order->products->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name ?? '-',
                        'quantity' => (float) $item->quantity,
                        'price' => (float) $item->price,
                        'discounted_price' => (float) $item->discounted_price,
                        'sale_tax' => (float) ($item->sale_tax ?? 0),
                        'further_tax' => (float) ($item->further_tax ?? 0),
                        'total' => (float) $item->total,
                    ];
                }),

                'sub_total' => (float) $order->sub_total,
                'total_sale_tax' => (float) ($order->total_sale_tax ?? 0),
                'total_further_tax' => (float) ($order->total_further_tax ?? 0),
                'discount' => (float) $order->discount,
                'total' => (float) $order->total,
                'paid' => (float) $order->paid,
                'due' => (float) $order->due,

                'type' => $order->type,
                'type_label' => $order->type == 'pos_invoice' ? 'POS' : 'Digital',

                'payment_status' => $order->status ? 'paid' : 'due',

                'fbr_status' => $this->getFbrStatus($order, $fbrIntegration),
                'fbr_invoice_no' => $fbrIntegration == 'enabled'
                    ? $order->fbr_invoice_no
                    : null,

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


    public function storeScheduler(Request $request)
    {
       

        $data = $request->validate([
            'business_id' => ['required', 'integer', 'exists:businesses,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'user_id' => [ 'integer'],
            'order_date' => ['required', 'date_format:Y-m-d'],
            'order_stage' => ['nullable', 'in:draft,submitted'],
            'order_discount' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);


        // IMPORTANT: make sure this user is allowed to use this business
        // abort_unless(
        //     $request->user()->businesses()->whereKey($data['business_id'])->exists(),
        //     403,
        //     'You do not have access to this business'
        // );

        $data['order_stage'] = 'submitted';
        $data['is_fbr_enabled'] = true;
        $userId = 0;

        try {
            $result = $this->orders->create($data, $userId);
        } catch (InsufficientStockException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (FbrRejectedException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'error_code' => $e->fbrCode,
                'fbr_api_response' => $e->fbrResponse,
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Customer or business not found'], 404);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'Something went wrong'], 500);
        }

        return response()->json([
            'message' => 'Order completed successfully',
            'order' => $result['order'],
            'fbr_invoice_no' => $result['fbr']['invoice_number'] ?? null,
            'fbr_api_response' => $result['fbr']['raw'] ?? null,
        ], 201);
    }

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

    $qtyVal = \App\Models\quantity_validation::where('business_id', $businessId)->first();
    $isStockEnforced = $qtyVal && $qtyVal->product_quantity_validation === 'disabled';
    

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

        $duplicateOrder = $this->duplicateOrderService->findDuplicate(
            $request->customer_id,
            $businessId,
            $carts
        );

        if ($duplicateOrder) {
            return response()->json([
                'duplicate' => true,
                'message' => 'Same order already exists. Do you want to create it again?',
            ]);
        }

        return response()->json([
            'duplicate' => false,
        ]);
    }

    /* ================= ORDER TRANSACTION ================= */
    DB::beginTransaction();

    $counter = BusinessCounter::lockForUpdate()
        ->firstOrCreate(
            ['business_id' => $businessId],
            ['last_invoice_number' => 0]
        );

    $counter->increment('last_invoice_number');

    $invoiceNumber = $counter->last_invoice_number;

    $order = Order::create([
        'customer_id' => $request->customer_id,
        'user_id'     => auth()->id(),
        'business_id' => $businessId,
        'invoice_number' => $invoiceNumber,
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
        $subTotal  = round($unitPrice * $qty, 2);
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
        
            $lastProcessedPi = null;
            foreach ($purchaseItems as $pi) {
                if ($qtyToConsume <= 0) break;
        
                $consume = min($pi->remaining_qty, $qtyToConsume);
                $pi->decrement('remaining_qty', $consume);
                $qtyToConsume -= $consume;
                $lastProcessedPi = $pi;
            }
        
            if ($qtyToConsume > 0) {
                if ($isStockEnforced) {
                    DB::rollBack();
                    return response()->json([
                        'error' => "Insufficient stock for product: {$product->name}"
                    ], 422);
                } else {
                    if ($lastProcessedPi) {
                        $lastProcessedPi->decrement('remaining_qty', $qtyToConsume);
                    } else {
                        $latestBatch = PurchaseItem::where('product_id', $product->id)
                            ->orderBy('created_at', 'desc')
                            ->first();
                        if ($latestBatch) {
                            $latestBatch->decrement('remaining_qty', $qtyToConsume);
                        }
                    }
                }
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
        "USIN" => "USIN-" . $order->business_id . "-" . str_pad($order->invoice_number, 4, '0', STR_PAD_LEFT),
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
            info('Connection Exception: ' . $e->getMessage());
            // dd("im here"); 
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

    $qtyVal = \App\Models\quantity_validation::where('business_id', $businessId)->first();
    $isStockEnforced = $qtyVal && $qtyVal->product_quantity_validation === 'disabled';

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

    $counter = BusinessCounter::lockForUpdate()
        ->firstOrCreate(
            ['business_id' => $businessId],
            ['last_invoice_number' => 0]
        );

    $counter->increment('last_invoice_number');

    $invoiceNumber = $counter->last_invoice_number;

    $order = Order::create([
        'customer_id' => $request->customer_id,
        'user_id'     => auth()->id(),
        'business_id' => $businessId,
        'invoice_number' => $invoiceNumber,
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
        
            $lastProcessedPi = null;
            foreach ($purchaseItems as $pi) {
                if ($qtyToConsume <= 0) break;
        
                $consume = min($pi->remaining_qty, $qtyToConsume);
                $pi->decrement('remaining_qty', $consume);
                $qtyToConsume -= $consume;
                $lastProcessedPi = $pi;
            }
        
            if ($qtyToConsume > 0) {
                if ($isStockEnforced) {
                    DB::rollBack();
                    return response()->json([
                        'error' => "Insufficient stock for product: {$product->name}"
                    ], 422);
                } else {
                    if ($lastProcessedPi) {
                        $lastProcessedPi->decrement('remaining_qty', $qtyToConsume);
                    } else {
                        $latestBatch = PurchaseItem::where('product_id', $product->id)
                            ->orderBy('created_at', 'desc')
                            ->first();
                        if ($latestBatch) {
                            $latestBatch->decrement('remaining_qty', $qtyToConsume);
                        }
                    }
                }
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
    "invoiceRefNo" => "INV-" . $order->business_id . "-" . str_pad($order->invoice_number, 4, '0', STR_PAD_LEFT),
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
     
}