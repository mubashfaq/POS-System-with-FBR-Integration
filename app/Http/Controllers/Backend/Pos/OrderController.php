<?php

namespace App\Http\Controllers\Backend\Pos;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\PosCart;
use App\Models\Product;
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
                ->addColumn('status', fn($data) => $data->status
                    ? '<span class="badge bg-primary">Paid</span>'
                    : '<span class="badge bg-danger">Due</span>')
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
                ->rawColumns(['saleId', 'customer', 'item', 'sub_total', 'discount', 'total', 'paid', 'due', 'status', 'action'])
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

    public function store(Request $request)
{
    $request->validate([
        'customer_id' => [
            'required',
            'exists:customers,id',
            'integer',
        ],
        'order_discount' => [
            'nullable',
            'numeric',
            'min:0',
        ],
        'paid' => [
            'nullable',
            'numeric',
            'min:0',
        ],
    ], [
        'customer_id.required' => 'Please select a customer.',
        'customer_id.exists'   => 'The selected customer does not exist.',
        'order_discount.numeric' => 'The order discount must be a number.',
        'paid.numeric'           => 'The amount paid must be a number.',
    ]);

    $businessId = session('business_id'); // yahan se business_id mil raha hai

    if (!$businessId) {
        return response()->json(['error' => 'No business selected'], 422);
    }

    $carts = PosCart::with('product')->where('user_id', auth()->id())->get();

    

    if ($carts->isEmpty()) {
        return response()->json(['error' => 'Cart is empty'], 422);
    }
    // order create karte waqt business_id bhi insert hoga
    $order = Order::create([
        'customer_id' => $request->customer_id,
        'user_id'     => $request->user()->id,
        'business_id' => $businessId,
    ]);

    $totalAmountOrder = 0;
    $orderDiscount = $request->order_discount;

    foreach ($carts as $cart) {
        $product = $cart->product;
        $mainTotal = $product->price * $cart->quantity;
        $totalAfterDiscount = $product->discounted_price * $cart->quantity;
        $discount = $mainTotal - $totalAfterDiscount;
        $totalAmountOrder += $totalAfterDiscount;

        $order->products()->create([
            'quantity'       => $cart->quantity,
            'price'          => $cart->product->price,
            'purchase_price' => $cart->product->purchase_price,
            'sub_total'      => $mainTotal,
            'discount'       => $discount,
            'total'          => $totalAfterDiscount,
            'product_id'     => $cart->product->id,
        ]);

        $cart->product->quantity -= $cart->quantity;
        $cart->product->save();
    }

    $total = $totalAmountOrder - $orderDiscount;
    $due   = $total - $request->paid;

    $order->sub_total = $totalAmountOrder;
    $order->discount  = $orderDiscount;
    $order->paid      = $request->paid;
    $order->total     = round((float)$total, 2);
    $order->due       = round((float)$due, 2);
    $order->status    = round((float)$due, 2) <= 0;
    $order->save();

     // BUILD FBR ITEMS ARRAY
     $fbrItems = [];

     foreach ($carts as $cart) {
        
        $product = $cart->product;
        // fbr errror for 2 decimal 
        $quantity = round($cart->quantity, 4); 
        $lineTotal = round($product->discounted_price * $quantity, 2); // 2 decimals
       $rate_of_taxx = $product->rate_of_tax;
$rate_of_tax = trim($rate_of_taxx); // Remove spaces

if (str_ends_with($rate_of_tax, '%')) {
    // Percentage case
    $numericRate = floatval(str_replace('%', '', $rate_of_tax));

    $valueExTax = round($lineTotal / (1 + ($numericRate / 100)), 2);
    $salesTax   = round($lineTotal - $valueExTax, 2);

} elseif (str_starts_with($rate_of_tax, 'Rs')) {
    // Fixed amount case
    $numericRate = floatval(preg_replace('/[^0-9.]/', '', $rate_of_tax));

    $salesTax   = $numericRate;
    $valueExTax = round($lineTotal - $salesTax, 2);

} else {
    // Fallback, assume percentage
    $numericRate = floatval($rate_of_tax);

    $valueExTax = round($lineTotal / (1 + ($numericRate / 100)), 2);
    $salesTax   = round($lineTotal - $valueExTax, 2);
}

        $hsCode = $product->hsCode;
        $hs_desc = $product->hs_description;
        $uom = $product->uom;
        $sro_item = $product->sro_item;
        $sale_type = $product->sale_type;
        $sro_schedule = $product->sro_schedule;
        
        $fbrItems[] = [
            "hsCode" => $hsCode,
            "productDescription" => $hs_desc,
            "rate" => $rate_of_taxx,
            "uoM" => $uom,
            "quantity" => (float) number_format($quantity, 4, '.', ''),
            "totalValues" => (float) number_format($lineTotal, 2, '.', ''),
            "valueSalesExcludingST" => (float) number_format($valueExTax, 2, '.', ''),
            "fixedNotifiedValueOrRetailPrice" => 0,
            "salesTaxApplicable" => (float) number_format($salesTax, 2, '.', ''),
            // "salesTaxApplicable" => 9,
            // "salesTaxWithheldAtSource" => 5,
            "salesTaxWithheldAtSource" => (float) number_format($cart->salesTaxWithheldAtSource, 2, '.', ''),
            // numberic extra tax fbr 
            "extraTax" => 0.00,
            "furtherTax" => 0,
            "sroScheduleNo" => $sro_schedule,
            "fedPayable" => 0,
            "discount" => (float) number_format($discount, 2, '.', ''),
            // "discount" => $discount,
            "saleType" => $sale_type,
            // "saleType" => "Local Sale",
            "sroItemSerialNo" => $sro_item,
        ];
    }

    // BUILD FBR PAYLOAD
    $customer = Customer::find($request->customer_id);
     $businessId = session('business_id');
     $businessData = Business::find($businessId);

    $fbrPayload = [
        // "invoiceType" => "Goods at standard rate (default)",
        "invoiceType" => "Sale Invoice",
        "invoiceDate" => now()->format('Y-m-d'),

        // "sellerNTNCNIC" => "3520228369157",
        "sellerNTNCNIC" => $businessData->cnic,
        "sellerBusinessName" => $businessData->business_name,
        "sellerProvince" => $businessData->state,
        "sellerAddress" => $businessData->address,

        "buyerNTNCNIC" => "",
        "buyerBusinessName" => $customer->name,
        "buyerProvince" => $customer->province ?? "Punjab",
        "buyerAddress" => $customer->address ?? "",
        "buyerRegistrationType" => $customer->is_registered ? "Registered" : "Unregistered",

        "invoiceRefNo" => "INV-" . $order->id,
        "scenarioId" => "SN002",

        "items" => $fbrItems
    ];

    // dd($fbrPayload);
    // $this->checkUOMUsingHsCode();

    // SEND TO FBR API
    $token = env('FBR_TOKEN');


   try {
    $response = Http::withOptions([
    'timeout' => 620,        // total request timeout
    'connect_timeout' => 620, // connection timeout
    ])->withHeaders([
        'Authorization' => "Bearer $token",
        'Content-Type' => 'application/json',
        'Accept-Encoding' => 'gzip, deflate, br',
        'Accept' => 'application/json'
    ])->post("https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata_sb", $fbrPayload);

    } catch (\Illuminate\Http\Client\ConnectionException $e) {
        info('Connection Exception: ' . $e->getMessage());
}

    // RECORD PAYMENT (IF ANY)

    if ($request->paid > 0) {
        $order->transactions()->create([
            'amount'      => $request->paid,
            'customer_id' => $order->customer_id,
            'user_id'     => auth()->id(),
            'paid_by'     => 'cash',
        ]);
    }

    PosCart::where('user_id', auth()->id())->delete();

    // FINAL RESPONSE

    return response()->json([
        'message' => 'Order completed successfully!',
        // 'order' => $order,
        'fbr_payload_sent' => $fbrPayload,
        'fbr_api_response' => $response->json(),
    ], 200);

    // return response()->json(['message' => 'Order completed successfully', 'order' => $order], 200);
}

    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'customer_id' => [
    //             'required',
    //             'exists:customers,id',
    //             'integer', // Ensure customer_id is an integer
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
    //         'customer_id.exists' => 'The selected customer does not exist.',
    //         'order_discount.numeric' => 'The order discount must be a number.',
    //         'paid.numeric' => 'The amount paid must be a number.',
    //     ]);
    //     $carts = PosCart::with('product')->where('user_id', auth()->id())->get();
    //     $order = Order::create([
    //         'customer_id' => $request->customer_id,
    //         'user_id' => $request->user()->id,
    //     ]);
    //     $totalAmountOrder = 0;
    //     $orderDiscount = $request->order_discount;
    //     foreach ($carts as $cart) {
    //         $mainTotal = $cart->product->price * $cart->quantity;
    //         $totalAfterDiscount = $cart->product->discounted_price * $cart->quantity;
    //         $discount = $mainTotal - $totalAfterDiscount;
    //         $totalAmountOrder += $totalAfterDiscount;
    //         $order->products()->create([
    //             'quantity' => $cart->quantity,
    //             'price' => $cart->product->price,
    //             'purchase_price' => $cart->product->purchase_price,
    //             'sub_total' => $mainTotal,
    //             'discount' => $discount,
    //             'total' => $totalAfterDiscount,
    //             'product_id' => $cart->product->id,
    //         ]);
    //         $cart->product->quantity = $cart->product->quantity - $cart->quantity;
    //         $cart->product->save();
    //     }
    //     $total = $totalAmountOrder - $orderDiscount;
    //     $due = $total - $request->paid;
    //     $order->sub_total = $totalAmountOrder;
    //     $order->discount = $orderDiscount;
    //     $order->paid = $request->paid;
    //     $order->total = round((float)$total, 2);
    //     $order->due = round((float)$due, 2);
    //     $order->status = round((float)$due, 2) <= 0;
    //     $order->save();
    //     //create order transaction
    //     if ($request->paid > 0) {
    //         $orderTransaction = $order->transactions()->create([
    //             'amount' => $request->paid,
    //             'customer_id' => $order->customer_id,
    //             'user_id' => auth()->id(),
    //             'paid_by' => 'cash',
    //         ]);
    //     }

    //     $carts = PosCart::where('user_id', auth()->id())->delete();
    //     return response()->json(['message' => 'Order completed successfully', 'order' => $order], 200);
    // }

    /**
     * Display the specified resource.
     */
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
        return view('backend.orders.print-invoice', compact('order'));
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

     
}