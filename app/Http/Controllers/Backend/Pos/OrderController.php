<?php

namespace App\Http\Controllers\Backend\Pos;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessCounter;
use App\Models\BusinessCustomField;
use App\Models\Customer;
use App\Models\InvoiceSetting;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\OrderTransaction;
use App\Models\PosCart;
use App\Models\PurchaseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;
use App\Services\FbrTokenService;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\DuplicateOrderService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class OrderController extends Controller
{
    protected $duplicateOrderService;

    public function __construct(DuplicateOrderService $duplicateOrderService)
    {
        $this->duplicateOrderService = $duplicateOrderService;
    }
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {
        if ($request->ajax()) {

            $request->validate([
                'environment' => 'nullable|in:sandbox,production',
                'sale_status' => 'nullable|in:draft,success,failed',
            ]);

            $businessId = session('business_id');

            if (!$businessId) {
                return response()->json(['error' => 'No business selected'], 422);
            }

            $business = Business::find($businessId);

            $ordersQuery = Order::with('customer')->where('business_id', $businessId);

            // Date Filter
            $dateField = $request->input('date_field', 'created_at');

            if ($dateField === 'order_date') {
                $ordersQuery->whereNotNull('order_date');
            }

            if (!in_array($dateField, ['created_at', 'order_date'])) {
                $dateField = 'created_at';
            }

            if ($request->filled('start_date')) {
                $start_date = Carbon::createFromFormat('Y-m-d', $request->input('start_date'))->startOfDay();
                $ordersQuery->where($dateField, '>=', $start_date);
            }

            if ($request->filled('end_date')) {
                $end_date = Carbon::createFromFormat('Y-m-d', $request->input('end_date'))->endOfDay();
                $ordersQuery->where($dateField, '<=', $end_date);
            }

            // invoice_number filter
            if ($request->filled('invoice_number')) {

                $search = trim($request->input('invoice_number'));

                $ordersQuery->where(function ($q) use ($search) {

                    // FBR invoice number
                    $q->where('fbr_invoice_no', 'like', "%{$search}%")

                        // Custom invoice number
                        ->orWhere('custom_invoice_number', 'like', "%{$search}%")

                        // System-generated: match the "INV-{business_id}-{padded}" pattern
                        // Strategy: strip prefix, extract numeric part, compare to invoice_number column
                        ->orWhere(function ($sub) use ($search) {
                            // If user typed "INV-5-0007" extract 7 and match invoice_number
                            if (preg_match('/^INV-\d+-(\d+)$/i', $search, $m)) {
                                $sub->where('invoice_number', (int) $m[1]);
                            } else {
                                // Also allow plain number search: "7" → invoice_number = 7
                                $sub->where('invoice_number', 'like', "%{$search}%");
                            }
                        });
                });
            }

            // Sale Status Filter (Draft / Successful / Failed)
            if ($request->filled('sale_status')) {

                $saleStatus = $request->input('sale_status');

                if ($saleStatus === 'draft') {

                    $ordersQuery->where('order_stage', 'draft');

                } elseif ($saleStatus === 'success') {

                    // Mirrors the "Data Submitted" badge condition
                    $ordersQuery->where('order_stage', 'submitted')
                        ->where('api_status', 200)
                        ->where(function ($q) {
                            $q->whereNull('error_code')->orWhere('error_code', '');
                        })
                        ->where(function ($q) {
                            $q->whereNotNull('fbr_invoice_no')->where('fbr_invoice_no', '!=', '');
                        });

                } elseif ($saleStatus === 'failed') {
                    $ordersQuery->where('order_stage', 'submitted')
                        ->where(function ($q) {
                            $q->where('api_status', '!=', 200)
                                ->orWhere(function ($sub) {
                                    $sub->whereNotNull('error_code')->where('error_code', '!=', '');
                                })
                                ->orWhereNull('fbr_invoice_no')
                                ->orWhere('fbr_invoice_no', '');
                        });
                }
            }

            // Environment Logic
            $isSuperAdmin = auth()->user()->hasRole('super_admin');
            $environment = FbrTokenService::getURLEnvironment();

            // Other users → only current environment + null
            if (!$isSuperAdmin) {
                $ordersQuery->where(function ($query) use ($environment) {
                    $query->where('invoice_environment', $environment)->orWhereNull('invoice_environment');
                });
            }

            // Super Admin → optional environment filter
            if ($isSuperAdmin && $request->filled('environment')) {
                $ordersQuery->where('invoice_environment', $request->input('environment'));
            }

            $orders = $ordersQuery->get();

            $fbr = FbrTokenService::isIntegrationEnabled();

            $datatable = DataTables::of($orders)
                ->addIndexColumn()

                ->addColumn('saleId', fn($data) => "#" . $data->id)

                ->addColumn('invoice_number', function ($data) {
                    if (filled($data->fbr_invoice_no)) {
                        return '<span class="badge bg-success" title="FBR">' . e($data->fbr_invoice_no) . '</span>';
                    }
                    if (filled($data->custom_invoice_number)) {
                        return '<span class="badge bg-info" title="Custom">' . e($data->custom_invoice_number) . '</span>';
                    }
                    return '<span class="badge bg-secondary" title="System">' . e($data->display_invoice_number) . '</span>';
                })

                ->addColumn('customer', fn($data) => $data->customer->name ?? '-')

                ->addColumn('item', fn($data) => number_format($data->total_item, 2, '.', ','))

                ->addColumn('sub_total', fn($data) => number_format($data->sub_total, 2, '.', ','))

                ->addColumn('discount', fn($data) => number_format($data->discount, 2, '.', ','))

                ->addColumn('total', fn($data) => number_format($data->total, 2, '.', ','))

                ->addColumn('paid', fn($data) => number_format($data->paid, 2, '.', ','))

                ->addColumn('due', fn($data) => number_format($data->due, 2, '.', ','))

                /* Type*/
                ->addColumn('type', function ($data) use ($business) {

                    if ($business && $business->business_type === 'pral') {
                        return '<span class="badge bg-warning">PRA</span>';
                    }

                    return $data->type == 'pos_invoice'
                        ? '<span class="badge bg-info">POS</span>'
                        : '<span class="badge bg-secondary">Digital</span>';
                })

                ->addColumn('environment', function ($data) {
                    if ($data->invoice_environment === 'sandbox') {
                        return '<span class="badge bg-primary">Sandbox</span>';
                    }

                    if ($data->invoice_environment === 'production') {
                        return '<span class="badge bg-danger">Production</span>';
                    }

                    return ' - ';

                })

                ->addColumn(
                    'status',
                    fn($data) => $data->status
                        ? '<span class="badge bg-primary">Paid</span>'
                        : '<span class="badge bg-danger">Due</span>'
                )

                ->addColumn('date', fn($data) => $data->created_at->format('Y-m-d'));


            $datatable->addColumn('fbr_status', function ($d) {

                if (!$d->fbr_integration_enabled && $d->order_stage !== 'draft') {
                    return "
                            <button data-id='{$d->id}' class='fbr-status-disabled btn btn-md bg-white' title='FBR Disabled'>
                                <i class='fas fa-times-circle text-muted'></i>
                            </button>
                        ";
                }

                // Successfully submitted to FBR
                if ($d->api_status == 200 && empty($d->error_code) && !empty($d->fbr_invoice_no)) {
                    return "
                            <button data-id='{$d->id}' class='fbr-status btn btn-md bg-white' title='Data Submitted'>
                                <i class='fas fa-check-circle text-success'></i>
                            </button>
                        ";
                }

                // FBR rejected / data incorrect
                if ($d->api_status == 200 && !empty($d->error_code) && empty($d->fbr_invoice_no)) {
                    return "
                            <button data-id='{$d->id}' class='fbr-status btn btn-md bg-white' title='Data Not Correct For FBR'>
                                <i class='fas fa-times-circle text-danger'></i>
                            </button>
                        ";
                }

                // API failed
                if ($d->api_status != 200 && empty($d->fbr_invoice_no)) {
                    return "
                            <button data-id='{$d->id}' class='fbr-status btn btn-md bg-white' title='Api Failed'>
                                <i class='fas fa-exclamation-triangle text-warning'></i>
                            </button>
                        ";
                }

                return '';
            });

            /* Action Buttons */
            $datatable->addColumn('action', function ($data) use ($business, $fbr) {

                $isDraft =
                    $data->order_stage === 'draft' &&
                    !$data->is_cancelled;

                $buttons = [];

                /*
                |--------------------------------------------------------------------------
                | Send to FBR
                |
                | Only show when:
                | 1. FBR integration is enabled
                | 2. Order is draft
                | 3. Order is not cancelled
                |--------------------------------------------------------------------------
                */
                if ($fbr && $isDraft) {

                    $submitRoute = $this->resolveSubmitRoute(
                        $business,
                        $data
                    );

                    $buttons[] = '
                        <button
                            type="button"
                            class="btn-expand btn-primary btn-send-fbr"
                            data-id="' . $data->id . '"
                            data-url="' . $submitRoute . '"
                        >
                            <i class="fas fa-paper-plane"></i>
                            <span>Send to FBR</span>
                        </button>
                    ';
                }

                if ($data->type == 'pos_invoice') {

                    $buttons[] = '
                        <a
                            class="btn-expand btn-outline-success"
                            href="' . route(
                        'backend.admin.orders.invoice',
                        $data->id
                    ) . '"
                        >
                            <i class="fas fa-file-invoice"></i>
                            <span>Invoice</span>
                        </a>
                    ';

                    $buttons[] = '
                        <a
                            class="btn-expand btn-outline-secondary"
                            href="' . route(
                        'backend.admin.orders.pos-invoice',
                        $data->id
                    ) . '"
                        >
                            <i class="fas fa-receipt"></i>
                            <span>Pos Invoice</span>
                        </a>
                    ';

                } else {

                    $buttons[] = '
                        <a class="btn-expand btn-outline-success" href="' . route('backend.admin.orders.pos-receipt-digital', $data->id) . '">
                            <i class="fas fa-file-invoice"></i>
                            <span>Receipt</span>
                        </a>
                    ';

                    $buttons[] = '
                        <a
                            class="btn-expand btn-outline-secondary"
                            href="' . route(
                        'backend.admin.orders.pos-invoice-digital',
                        $data->id
                    ) . '"
                        >
                            <i class="fas fa-receipt"></i>
                            <span>Digital Invoice</span>
                        </a>
                    ';
                }

                // $buttons[] = '
                //     <a
                //         class="btn-expand btn-outline-info"
                //         href="' . route(
                //             'backend.admin.orders.customer-tax-invoice',
                //             $data->id
                //         ) . '"
                //     >
                //         <i class="fas fa-file-invoice-dollar"></i>
                //         <span>Customer Tax Invoice</span>
                //     </a>
                // ';

                if (!$data->is_cancelled && !$data->status) {

                    $buttons[] = '
                        <a
                            class="btn-expand btn-outline-warning"
                            href="' . route(
                        'backend.admin.due.collection',
                        $data->id
                    ) . '"
                        >
                            <i class="fas fa-receipt"></i>
                            <span>Due Collection</span>
                        </a>
                    ';
                }

                $buttons[] = '
                    <a
                        class="btn-expand btn-outline-primary"
                        href="' . route(
                    'backend.admin.orders.transactions',
                    $data->id
                ) . '"
                    >
                        <i class="fas fa-exchange-alt"></i>
                        <span>Transactions</span>
                    </a>
                ';

                if (!$data->is_cancelled) {

                    $buttons[] = '
                        <button
                            type="button"
                            class="btn-expand btn-outline-danger btn-cancel-order"
                            data-id="' . $data->id . '"
                        >
                            <i class="fas fa-times-circle"></i>
                            <span>Cancel Order</span>
                        </button>
                    ';

                } else {

                    $buttons[] = '
                        <span class="btn-expand btn-danger" style="pointer-events:none;">
                            <i class="fas fa-ban"></i>
                            <span>Cancelled</span>
                        </span>
                    ';
                }

                return '<div class="d-flex gap-1 flex-nowrap">'
                    . implode(' ', $buttons)
                    . '</div>';
            });

            /* Raw Column */
            $rawColumns = [
                'saleId',
                'invoice_number',
                'customer',
                'item',
                'sub_total',
                'discount',
                'total',
                'paid',
                'due',
                'type',
                'date',
                'environment',
                'status',
                'fbr_status',
                'action',
            ];

            $datatable->rawColumns($rawColumns);

            return $datatable->toJson();
        }

        $fbr = FbrTokenService::isIntegrationEnabled();

        return view('backend.orders.index', compact('fbr'));
    }

    /**
     * Decide which submit endpoint applies to this order.
     */
    private function resolveSubmitRoute($business, $order): string
    {
        if (strtolower($business->business_type) === 'pral') {
            return route('backend.admin.orders.submit-pral', $order->id);
        }

        // business_type: pos / digital / both
        if ($order->type === 'pos_invoice') {
            return route('backend.admin.orders.submit-pos', $order->id);
        }

        return route('backend.admin.orders.submit-digital', $order->id);
    }

    public function downloadPdf(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return back()->with('error', 'No orders selected.');
        }

        $orders = Order::with('customer')
            ->whereIn('id', $ids)
            ->where('business_id', session('business_id'))
            ->orderBy('id', 'desc')
            ->get();

        $pdf = Pdf::loadView('backend.orders.pdf', compact('orders'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('Sale List - ' . readConfig('site_name') . '.pdf');
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
            'order_stage' => ['nullable', 'in:draft,submitted'],
            'orderDate' => ['required', 'date', 'date_format:Y-m-d'],
        ]);

        $userId = auth()->id();

        $businessId = session('business_id');

        if (!$businessId) {
            return response()->json(['error' => 'No business selected'], 422);
        }

        $qtyVal = \App\Models\quantity_validation::where('business_id', $businessId)->first();
        $isStockEnforced = $qtyVal && $qtyVal->product_quantity_validation === 'disabled';

        $customer = Customer::find($request->customer_id);

        $order_stage = $request->order_stage;


        $carts = PosCart::with(['product', 'purchaseItem'])
            ->where('user_id', auth()->id())
            ->lockForUpdate()
            ->get();

        if ($carts->isEmpty()) {
            return response()->json(['error' => 'Cart is empty'], 422);
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
        $getURLEnvironment = FbrTokenService::getURLEnvironment();
        $POSID = FbrTokenService::getPOSID();
        $isFbrIntegrationEnabled = FbrTokenService::isIntegrationEnabled();

        $order = Order::create([
            'customer_id' => $request->customer_id,
            'user_id' => $userId,
            'business_id' => $businessId,
            'invoice_number' => $invoiceNumber,
            'order_date' => $request->orderDate,
            'invoice_environment' => $getURLEnvironment,
            'POSID' => $POSID,
            'fbr_integration_enabled' => $isFbrIntegrationEnabled,
        ]);

        $totalAmountOrder = 0;
        $totalTaxOrder = 0;
        $totalCustomerTaxOrder = 0;
        $orderDiscount = $request->order_discount ?? 0;

        // Separate parent items from accessory items
        $parentCarts = $carts->filter(fn($c) => is_null($c->parent_cart_id));
        $accessoryCarts = $carts->filter(fn($c) => !is_null($c->parent_cart_id));

        // Map parent cart id → order_product id so accessories can reference it
        $parentCartToOrderProduct = [];
        $parentCartToQty = [];

        // ── PARENT ITEMS: price calculation + order_products insert ──
        foreach ($parentCarts as $cart) {
            $product = $cart->product;

            // Use price_override if exists
            $unitPrice = $cart->price_override ?? $product->discounted_price;
            $qty = $cart->quantity;
            $lineTotal = round($unitPrice * $qty, 2);
            $subTotal = round($product->price * $qty, 2);
            $discount = round(($product->price - $unitPrice) * $qty, 2);

            // Sale TAX
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

            // Customer Tax
            $customerTax = 0;

            if ($customer->customer_tax_type === 'retailer') {
                $customerTax = round($finalTotal * 0.005, 2);
            } elseif ($customer->customer_tax_type === 'distributor') {
                $customerTax = round($finalTotal * 0.01, 2);
            }

            $totalAmountOrder += $finalTotal;
            $totalTaxOrder += $saleTax;
            $totalCustomerTaxOrder += $customerTax;

            $orderProduct = $order->products()->create([
                'quantity' => $cart->quantity,
                'hsCode' => $product->hsCode ?? null,
                'uom' => $product->uom ?? null,
                'price' => $unitPrice,
                'purchase_price' => $cart->purchaseItem ? $cart->purchaseItem->purchase_price : $product->purchase_price,
                'sub_total' => $subTotal,
                'discount' => $discount,
                'tax_rate' => $rateText,
                'sale_tax' => $saleTax,
                'customer_tax' => $customerTax,
                'further_tax' => 0,
                'total' => $finalTotal,
                'product_id' => $product->id,
                'purchase_item_id' => $cart->purchase_item_id,
                'is_accessory' => false,
            ]);

            $parentCartToOrderProduct[$cart->id] = $orderProduct->id;
            $parentCartToQty[$cart->id] = $cart->quantity;

            // Decrement product stock
            $product->quantity -= $cart->quantity;
            $product->save();

            // Decrement batch remaining_qty if batch is linked
            if ($cart->purchase_item_id && $cart->purchaseItem) {
                $cart->purchaseItem->decrement('remaining_qty', $cart->quantity);
            } else {
                // FIFO: consume from oldest batches
                $qtyToConsume = $cart->quantity;
                $purchaseItems = PurchaseItem::where('product_id', $product->id)
                    ->where('remaining_qty', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->get();

                $lastProcessedPi = null;
                foreach ($purchaseItems as $pi) {
                    if ($qtyToConsume <= 0)
                        break;
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
        }

        // ── ACCESSORY ITEMS: stock decrement only, no price contribution ──
        foreach ($accessoryCarts as $cart) {
            $product = $cart->product;

            // Resolve parent order_product id
            $parentOrderProductId = $parentCartToOrderProduct[$cart->parent_cart_id] ?? null;

            $parentQty = $parentCartToQty[$cart->parent_cart_id] ?? 1;
            $scaledQty = $cart->quantity * $parentQty;

            $order->products()->create([
                'quantity' => $scaledQty,
                'price' => 0,
                'purchase_price' => $cart->purchaseItem ? $cart->purchaseItem->purchase_price : $product->purchase_price,
                'sub_total' => 0,
                'discount' => 0,
                'tax_rate' => 0,
                'customer_tax' => 0,
                'sale_tax' => 0,
                'further_tax' => 0,
                'total' => 0,
                'product_id' => $product->id,
                'purchase_item_id' => $cart->purchase_item_id,
                'is_accessory' => true,
                'parent_order_product_id' => $parentOrderProductId,
            ]);

            // Decrement accessory stock
            $product->quantity -= $scaledQty;
            $product->save();

            if ($cart->purchase_item_id && $cart->purchaseItem) {
                $cart->purchaseItem->decrement('remaining_qty', $scaledQty);
            } else {
                // FIFO: consume from oldest batches
                $qtyToConsume = $scaledQty;
                $purchaseItems = PurchaseItem::where('product_id', $product->id)
                    ->where('remaining_qty', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->get();

                $lastProcessedPi = null;
                foreach ($purchaseItems as $pi) {
                    if ($qtyToConsume <= 0)
                        break;
                    $consume = min($pi->remaining_qty, $qtyToConsume);
                    $pi->decrement('remaining_qty', $consume);
                    $qtyToConsume -= $consume;
                    $lastProcessedPi = $pi;
                }

                if ($qtyToConsume > 0) {
                    if ($isStockEnforced) {
                        DB::rollBack();
                        return response()->json([
                            'error' => "Insufficient stock for accessory: {$product->name}"
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
        }

        $total = $totalAmountOrder - $orderDiscount;
        $due = $total - ($request->paid ?? 0);

        $order->update([
            'sub_total' => $totalAmountOrder,
            'discount' => $orderDiscount,
            'total_sale_tax' => round($totalTaxOrder, 2),
            'total_customer_tax' => round($totalCustomerTaxOrder, 2),
            'total_further_tax' => 0,
            'paid' => $request->paid,
            'total' => round($total, 2),
            'due' => round($due, 2),
            'status' => round($due, 2) <= 0,
            'order_stage' => $order_stage,
            'type' => 'pos_invoice',
        ]);

        if ($order_stage == 'submitted') {

            /* ================= FBR ITEMS ================= */
            $fbrItems = [];
            $lineTotal = 0;
            foreach ($carts as $cart) {
                $product = $cart->product;


                $qty = round($cart->quantity, 2);
                $unitPrice = $cart->price_override ?? $product->discounted_price;
                $lineTotal = round($unitPrice * $qty, 2);

                $rateText = trim($product->pos_sale_tax); // 18%
                $taxRate = (float) str_replace('%', '', $rateText); // 18
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
                    "TotalAmount" => $lineTotal + $salesTax,
                    "TaxCharged" => $salesTax,
                    "discount" => 0,
                    "InvoiceType" => 1,
                ];
            }

            $customer = Customer::find($request->customer_id);
            $business = Business::find($businessId);

            $fbrPayload = [
                // "POSID" => 819045,
                "POSID" => $POSID,
                "USIN" => "USIN-" . $order->business_id . "-" . str_pad($order->invoice_number, 4, '0', STR_PAD_LEFT),
                "DateTime" => $request->orderDate,
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


            if (!$isFbrIntegrationEnabled) {

                DB::commit();

                PosCart::where('user_id', auth()->id())->delete();

                return response()->json([
                    'message' => 'Order completed successfully (FBR Not Integrated)',
                    'order' => $order,
                ], 200);

            } else {
                $tokens = FbrTokenService::getToken();

                if (is_array($tokens)) {
                    $token = $tokens['pos_token'];
                } else {
                    $token = $tokens;
                }

                $post_api_response = null;

                if ($business->business_type == 'pral') {
                    try {
                        $post_api_response = Http::withOptions([
                            'timeout' => 620,
                            'connect_timeout' => 620,
                            'verify' => false,
                        ])->withHeaders([
                                    'Authorization' => "Bearer $token",
                                    'Content-Type' => 'application/json',
                                    'Accept-Encoding' => 'gzip, deflate, br',
                                    'Accept' => '*/*'
                                ])->withBody(json_encode($fbrPayload), 'application/json')
                            ->post("https://ims.pral.com.pk/ims/{$getURLEnvironment}/api/Live/PostData");

                    } catch (\Throwable $e) {
                        DB::rollBack();

                        return response()->json([
                            'error' => 'FBR request failed',
                            'message' => $e->getMessage(),
                        ], 500);
                    }
                } else {

                    try {
                        $post_api_response = Http::withOptions([
                            'timeout' => 620,
                            'connect_timeout' => 620,
                            'verify' => false,
                        ])
                            ->withToken($token)
                            ->acceptJson()
                            ->post(
                                "https://ims.pral.com.pk/ims/{$getURLEnvironment}/api/Live/PostData",
                                $fbrPayload
                            );
                    } catch (\Throwable $e) {
                        dd([
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ]);
                    }
                }



                $postResponseJson = $post_api_response->json();

                /* ================= FBR API CALL ================= */
                Log::info('FBR Payload', [
                    'order_id' => $order->id ?? null,
                    'payload' => $fbrPayload,
                    'post_response_pos' => $postResponseJson,
                ]);

                $fbr_invoice_number = data_get($postResponseJson, 'InvoiceNumber');


                if ($postResponseJson['Response'] == "Invoice received successfully") {
                    DB::commit(); // save order data in db now 

                    Order::where('id', $order->id)->update([
                        'fbr_invoice_no' => $fbr_invoice_number,
                        'api_status' => $post_api_response->status(),
                        'api_message' => 1,
                    ]);

                    Log::channel('fbr')->info('FBR Payload', [
                        'order_id' => $order->id ?? null,
                        'user_id' => $userId,
                        'business_id' => $businessId,
                        'payload' => $fbrPayload,
                        'post_response_pos' => $postResponseJson['Response'],
                    ]);

                    OrderLog::create([
                        'order_id' => $order->id,
                        'user_id' => $userId,
                        'business_id' => $businessId,
                        'request_payload' => $fbrPayload,
                        'response' => $postResponseJson['Response'],
                        'error_code' => $postResponseJson['Code'] ?? null,
                        'error_message' => $postResponseJson['Response'] ?? null,
                    ]);
                } else {


                    Log::channel('fbr')->info('FBR Payload', [
                        'order_id' => $order->id ?? null,
                        'user_id' => $userId,
                        'business_id' => $businessId,
                        'payload' => $fbrPayload,
                        'post_response_pos' => $postResponseJson['Response'],
                    ]);

                    OrderLog::create([
                        'order_id' => $order->id,
                        'user_id' => $userId,
                        'business_id' => $businessId,
                        'request_payload' => $fbrPayload,
                        'response' => $postResponseJson['Response'],
                        'error_code' => $postResponseJson['Code'] ?? null,
                        'error_message' => $postResponseJson['Response'] ?? null,
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

        } else {


            DB::commit();

            PosCart::where('user_id', auth()->id())->delete(); // hamesha clear, chahe draft ho ya final

            return response()->json([
                'message' => 'Order saved as draft',
                'order' => $order,
            ], 200);

        }

    }

    public function submitPOSOrder(Request $request, $orderId)
    {
        $businessId = session('business_id');

        if (!$businessId) {
            return response()->json(['error' => 'No business selected'], 422);
        }

        $order = Order::with('products.product', 'products.purchaseItem')
            ->where('id', $orderId)
            ->where('business_id', $businessId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($order->is_cancelled) {
            return response()->json(['error' => 'Order is cancelled'], 422);
        }

        if ($order->order_stage === 'submitted') {
            return response()->json(['error' => 'Order already submitted'], 422);
        }

        $customer = Customer::find($order->customer_id);

        if (!$customer) {
            return response()->json(['error' => 'Customer not found for this order'], 422);
        }

        $business = Business::find($order->business_id);

        if (!$business) {
            return response()->json(['error' => 'Business not found for this order'], 422);
        }

        DB::beginTransaction();

        /* ================= FBR ITEMS ================= */
        $fbrItems = [];
        $totalQty = 0;
        $totalSaleValue = 0;
        $totalTaxCharged = 0;
        $totalBillAmount = 0;

        foreach ($order->products as $op) {
            $product = $op->product;

            $qty = round($op->quantity, 2);
            $lineTotal = round($op->price * $qty, 2);

            $rateText = trim($op->tax_rate);
            $taxRate = (float) str_replace('%', '', $rateText);
            $valueExTax = $lineTotal;

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
                "TotalAmount" => $lineTotal + $salesTax,
                "TaxCharged" => $salesTax,
                "discount" => 0,
                "InvoiceType" => 1,
            ];
            $totalQty += $qty;
            $totalSaleValue += $valueExTax;
            $totalTaxCharged += $salesTax;
            $totalBillAmount += $lineTotal + $salesTax;
        }

        $POSID = FbrTokenService::getPOSID();

        $fbrPayload = [
            // "POSID" => 819045,
            "POSID" => $POSID,
            "USIN" => "USIN-" . $order->business_id . "-" . str_pad($order->invoice_number, 4, '0', STR_PAD_LEFT),
            "DateTime" => now()->format('Y-m-d'),
            "buyerNTN" => $customer->ntn_cnic,
            "buyerCNIC" => $customer->ntn_cnic,
            "BuyerName" => $customer->name,
            "BuyerPhoneNumber" => $customer->phone,
            "items" => $fbrItems,
            "TotalBillAmount" => $totalBillAmount,
            "TotalQuantity" => $totalQty,
            "TotalSaleValue" => $totalSaleValue,
            "TotalTaxCharged" => $totalTaxCharged,
            "PaymentMode" => 2,
            "InvoiceType" => 1,
        ];

        $tokens = FbrTokenService::getToken();
        $token = is_array($tokens) ? $tokens['pos_token'] : $tokens;
        $getURLEnvironment = FbrTokenService::getURLEnvironment();


        $post_api_response = null;

        try {
            $post_api_response = Http::withOptions([
                'timeout' => 620,
                'connect_timeout' => 620,
                'verify' => false,
            ])
                ->withToken($token)
                ->acceptJson()
                ->post(
                    "https://ims.pral.com.pk/ims/{$getURLEnvironment}/api/Live/PostData", // <-- confirm: ye ya esp.fbr.gov.pk wala?
                    $fbrPayload
                );
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'FBR request failed',
                'message' => $e->getMessage(),
            ], 500);
        }

        if (!$post_api_response->successful()) {
            DB::rollBack();

            Log::channel('fbr')->error('FBR HTTP error', [
                'order_id' => $order->id,
                'status' => $post_api_response->status(),
                'body' => $post_api_response->body(),
            ]);

            $decodedBody = json_decode($post_api_response->body(), true);


            return response()->json([
                'error' => 'FBR API returned an HTTP error',
                'status' => $post_api_response->status(),
                'body' => $decodedBody ?? $post_api_response->body(),
            ], 502);
        }

        $postResponseJson = $post_api_response->json();

        Log::info('FBR Payload', [
            'order_id' => $order->id,
            'payload' => $fbrPayload,
            'post_response_pos' => $postResponseJson['Response'] ?? null,
        ]);

        $fbr_invoice_number = data_get($postResponseJson, 'InvoiceNumber');

        if (($postResponseJson['Response'] ?? null) == "Invoice received successfully") {

            $order->update([
                'fbr_invoice_no' => $fbr_invoice_number,
                'api_status' => $post_api_response->status(),
                'api_message' => 1,
                'order_stage' => 'submitted',
                'POSID' => $POSID,
            ]);

            DB::commit();

            Log::channel('fbr')->info('FBR Payload', [
                'order_id' => $order->id,
                'user_id' => auth()->id(),
                'business_id' => $businessId,
                'payload' => $fbrPayload,
                'post_response_pos' => $postResponseJson['Response'],
            ]);

            OrderLog::create([
                'order_id' => $order->id,
                'user_id' => auth()->id(),
                'business_id' => $businessId,
                'request_payload' => $fbrPayload,
                'response' => $postResponseJson['Response'],
                'error_code' => $postResponseJson['Code'] ?? null,
                'error_message' => $postResponseJson['Response'] ?? null,
            ]);

            return response()->json([
                'message' => 'Order completed successfully!',
                'fbr_payload_sent' => $fbrPayload,
                'fbr_api_response' => $postResponseJson,
                'order' => $order,
            ], 200);

        } else {

            DB::rollBack();

            Log::channel('fbr')->info('FBR Payload', [
                'order_id' => $order->id,
                'user_id' => auth()->id(),
                'business_id' => $businessId,
                'payload' => $fbrPayload,
                'post_response_pos' => $postResponseJson['Response'] ?? null,
            ]);

            OrderLog::create([
                'order_id' => $order->id,
                'user_id' => auth()->id(),
                'business_id' => $businessId,
                'request_payload' => $fbrPayload,
                'response' => $postResponseJson['Response'] ?? null,
                'error_code' => $postResponseJson['Code'] ?? null,
                'error_message' => $postResponseJson['Response'] ?? null,
            ]);

            return response()->json([
                'error' => $postResponseJson['Response'] ?? 'Unknown FBR error',
                'error_code' => $postResponseJson['Code'] ?? null,
                'error_message' => $postResponseJson['Response'] ?? null,
                'fbr_api_response' => $postResponseJson,
            ], 422);
        }
    }

    public function submitPRALOrder(Request $request, $orderId)
    {
        $businessId = session('business_id');

        if (!$businessId) {
            return response()->json(['error' => 'No business selected'], 422);
        }

        $order = Order::with('products.product', 'products.purchaseItem')
            ->where('id', $orderId)
            ->where('business_id', $businessId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($order->is_cancelled) {
            return response()->json(['error' => 'Order is cancelled'], 422);
        }

        if ($order->order_stage === 'submitted') {
            return response()->json(['error' => 'Order already submitted'], 422);
        }

        $customer = Customer::find($order->customer_id);

        if (!$customer) {
            return response()->json(['error' => 'Customer not found for this order'], 422);
        }

        $business = Business::find($order->business_id);

        if (!$business) {
            return response()->json(['error' => 'Business not found for this order'], 422);
        }

        DB::beginTransaction();

        /* ================= FBR ITEMS ================= */
        $fbrItems = [];
        $totalQty = 0;
        $totalSaleValue = 0;
        $totalTaxCharged = 0;
        $totalBillAmount = 0;

        foreach ($order->products as $op) {
            $product = $op->product;

            $qty = round($op->quantity, 2);
            $lineTotal = round($op->price * $qty, 2);

            $rateText = trim($op->tax_rate);
            $taxRate = (float) str_replace('%', '', $rateText);
            $valueExTax = $lineTotal;

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
                "TotalAmount" => $lineTotal + $salesTax,
                "TaxCharged" => $salesTax,
                "discount" => 0,
                "InvoiceType" => 1,
            ];
            $totalQty += $qty;
            $totalSaleValue += $valueExTax;
            $totalTaxCharged += $salesTax;
            $totalBillAmount += $lineTotal + $salesTax;
        }

        $POSID = FbrTokenService::getPOSID();


        $fbrPayload = [
            // "POSID" => 819045,
            "POSID" => $POSID,
            "USIN" => "USIN-" . $order->business_id . "-" . str_pad($order->invoice_number, 4, '0', STR_PAD_LEFT),
            "DateTime" => $order->order_date->format('Y-m-d'),
            "buyerNTN" => $customer->ntn_cnic,
            "buyerCNIC" => $customer->ntn_cnic,
            "BuyerName" => $customer->name,
            "BuyerPhoneNumber" => $customer->phone,
            "items" => $fbrItems,
            "TotalBillAmount" => $totalBillAmount,
            "TotalQuantity" => $totalQty,
            "TotalSaleValue" => $totalSaleValue,
            "TotalTaxCharged" => $totalTaxCharged,
            "PaymentMode" => 2,
            "InvoiceType" => 1,
        ];

        $tokens = FbrTokenService::getToken();
        $token = is_array($tokens) ? $tokens['pos_token'] : $tokens;
        $getURLEnvironment = FbrTokenService::getURLEnvironment();

        $post_api_response = null;

        try {
            $post_api_response = Http::withOptions([
                'timeout' => 620,
                'connect_timeout' => 620,
                'verify' => false,
            ])->withHeaders([
                        'Authorization' => "Bearer $token",
                        'Content-Type' => 'application/json',
                        'Accept-Encoding' => 'gzip, deflate, br',
                        'Accept' => '*/*'
                    ])->withBody(json_encode($fbrPayload), 'application/json')
                ->post("https://ims.pral.com.pk/ims/{$getURLEnvironment}/api/Live/PostData");

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'FBR request failed',
                'message' => $e->getMessage(),
            ], 500);
        }

        if (!$post_api_response->successful()) {
            DB::rollBack();

            Log::channel('fbr')->error('FBR HTTP error', [
                'order_id' => $order->id,
                'status' => $post_api_response->status(),
                'body' => $post_api_response->body(),
            ]);

            $decodedBody = json_decode($post_api_response->body(), true);

            return response()->json([
                'error' => 'FBR API returned an HTTP error',
                'status' => $post_api_response->status(),
                'body' => $decodedBody ?? $post_api_response->body(),
            ], 502);
        }

        $postResponseJson = $post_api_response->json();

        Log::info('FBR Payload', [
            'order_id' => $order->id,
            'payload' => $fbrPayload,
            'post_response_pos' => $postResponseJson['Response'] ?? null,
        ]);

        $fbr_invoice_number = data_get($postResponseJson, 'InvoiceNumber');

        if (($postResponseJson['Response'] ?? null) == "Invoice received successfully") {

            $order->update([
                'fbr_invoice_no' => $fbr_invoice_number,
                'api_status' => $post_api_response->status(),
                'api_message' => 1,
                'order_stage' => 'submitted',
                'POSID' => $POSID
            ]);

            DB::commit();

            Log::channel('fbr')->info('FBR Payload', [
                'order_id' => $order->id,
                'user_id' => auth()->id(),
                'business_id' => $businessId,
                'payload' => $fbrPayload,
                'post_response_pos' => $postResponseJson['Response'],
            ]);

            OrderLog::create([
                'order_id' => $order->id,
                'user_id' => auth()->id(),
                'business_id' => $businessId,
                'request_payload' => $fbrPayload,
                'response' => $postResponseJson['Response'],
                'error_code' => $postResponseJson['Code'] ?? null,
                'error_message' => $postResponseJson['Response'] ?? null,
            ]);

            return response()->json([
                'message' => 'Order completed successfully!',
                'fbr_payload_sent' => $fbrPayload,
                'fbr_api_response' => $postResponseJson,
                'order' => $order
            ], 200);

        } else {

            DB::rollBack();

            Log::channel('fbr')->info('FBR Payload', [
                'order_id' => $order->id,
                'user_id' => auth()->id(),
                'business_id' => $businessId,
                'payload' => $fbrPayload,
                'post_response_pos' => $postResponseJson['Response'] ?? null,
            ]);

            OrderLog::create([
                'order_id' => $order->id,
                'user_id' => auth()->id(),
                'business_id' => $businessId,
                'request_payload' => $fbrPayload,
                'response' => $postResponseJson['Response'] ?? null,
                'error_code' => $postResponseJson['Code'] ?? null,
                'error_message' => $postResponseJson['Response'] ?? null,
            ]);

            return response()->json([
                'error' => $postResponseJson['Response'] ?? 'Unknown FBR error',
                'error_code' => $postResponseJson['Code'] ?? null,
                'error_message' => $postResponseJson['Response'] ?? null,
                'fbr_api_response' => $postResponseJson,
            ], 422);
        }
    }


    public function storeDigital(Request $request)
    {
        $request->validate([
            'customer_id' => ['required', 'exists:customers,id', 'integer'],
            'order_discount' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'orderDate' => ['required', 'date', 'date_format:Y-m-d'],
            'order_stage' => ['nullable', 'in:draft,submitted']
        ]);

        $userId = auth()->id();
        $businessId = session('business_id');

        if (!$businessId) {
            return response()->json(['error' => 'No business selected'], 422);
        }

        $invoiceData = $this->validateCustomInvoiceNumber($request);

        $qtyVal = \App\Models\quantity_validation::where('business_id', $businessId)->first();
        $isStockEnforced = $qtyVal && $qtyVal->product_quantity_validation === 'disabled';

        $customer = Customer::find($request->customer_id);
        $order_stage = $request->order_stage;

        $carts = PosCart::with(['product', 'purchaseItem'])
            ->where('user_id', auth()->id())
            ->lockForUpdate()
            ->get();

        if ($carts->isEmpty()) {
            return response()->json(['error' => 'Cart is empty'], 422);
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

        $invoiceEnvironment = FbrTokenService::getURLEnvironment();
        $isFbrIntegrationEnabled = FbrTokenService::isIntegrationEnabled();


        $order = Order::create([
            'customer_id' => $request->customer_id,
            'user_id' => $userId,
            'business_id' => $businessId,
            'invoice_number' => $invoiceNumber,
            'note' => $request->note ?? null,
            'order_date' => $request->orderDate,
            'custom_invoice_number' => $invoiceData['custom_invoice_number'] ?? null,
            'po_number' => $request->po_number ?? null,
            'invoice_environment' => $invoiceEnvironment,
            'fbr_integration_enabled' => $isFbrIntegrationEnabled,
        ]);

        $totalAmountOrder = 0;
        $totalSaleTax = 0;
        $totalCustomerTaxOrder = 0;
        $totalFurtherTax = 0;
        $orderDiscount = $request->order_discount ?? 0;

        $parentCarts = $carts->filter(fn($c) => is_null($c->parent_cart_id));
        $accessoryCarts = $carts->filter(fn($c) => !is_null($c->parent_cart_id));

        $parentCartToOrderProduct = [];
        $parentCartToQty = [];

        // ── PARENT ITEMS ──
        foreach ($parentCarts as $cart) {
            $product = $cart->product;

            $unitPrice = $cart->price_override ?? $product->discounted_price;
            $qty = $cart->quantity;
            $lineTotal = round($unitPrice * $qty, 2);
            $subTotal = round($unitPrice * $qty, 2);
            $discount = round(($product->price - $unitPrice) * $qty, 2);

            $rateText = trim($product->rate_of_tax);
            $furtherTax = 0;

            if (str_ends_with($rateText, '%')) {
                $rate = floatval(str_replace('%', '', $rateText));
                $valueExTax = round($lineTotal, 2);
                $salesTax = round($valueExTax * ($rate / 100), 2);
            } else {
                $ratePerUnit = floatval(preg_replace('/[^0-9.]/', '', $rateText));
                $salesTax = round($ratePerUnit * $qty, 2);
                $valueExTax = round($lineTotal, 2);
            }

            if ($customer->is_registered && strtolower($customer->is_registered) === 'unregistered' && $product->sale_type != '3rd Schedule Goods') {
                $furtherTax = round($valueExTax * (4 / 100), 2);
            }

            $finalTotal = round($lineTotal + $salesTax + $furtherTax, 2);

            $customerTax = 0;
            if ($customer->customer_tax_type === 'retailer') {
                $customerTax = round($finalTotal * 0.005, 2);
            } elseif ($customer->customer_tax_type === 'distributor') {
                $customerTax = round($finalTotal * 0.01, 2); // 0.01 = (1/100) 1%
            }

            $totalAmountOrder += $finalTotal;
            $totalSaleTax += $salesTax;
            $totalFurtherTax += $furtherTax;
            $totalCustomerTaxOrder += $customerTax;

            $orderProduct = $order->products()->create([
                'quantity' => $cart->quantity,
                'hsCode' => $product->hsCode ?? null,
                'uom' => $product->uom ?? null,
                'price' => $unitPrice,
                'purchase_price' => $cart->purchaseItem ? $cart->purchaseItem->purchase_price : $product->purchase_price,
                'sub_total' => $subTotal,
                'discount' => $discount,
                'tax_rate' => $rateText,
                'sale_tax' => $salesTax,
                'customer_tax' => $customerTax,
                'further_tax' => $furtherTax,
                'total' => $finalTotal,
                'custom_fields' => $cart->custom_fields,
                'product_id' => $product->id,
                'purchase_item_id' => $cart->purchase_item_id,
                'is_accessory' => false,
            ]);

            $parentCartToOrderProduct[$cart->id] = $orderProduct->id;
            $parentCartToQty[$cart->id] = $cart->quantity;

            $product->quantity -= $cart->quantity;
            $product->save();

            if ($cart->purchase_item_id && $cart->purchaseItem) {
                $cart->purchaseItem->decrement('remaining_qty', $cart->quantity);
            } else {
                // FIFO: consume from oldest batches
                $qtyToConsume = $cart->quantity;
                $purchaseItems = PurchaseItem::where('product_id', $product->id)
                    ->where('remaining_qty', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->get();

                $lastProcessedPi = null;
                foreach ($purchaseItems as $pi) {
                    if ($qtyToConsume <= 0)
                        break;
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
        }

        // ── ACCESSORY ITEMS ──
        foreach ($accessoryCarts as $cart) {
            $product = $cart->product;

            $parentOrderProductId = $parentCartToOrderProduct[$cart->parent_cart_id] ?? null;
            $parentQty = $parentCartToQty[$cart->parent_cart_id] ?? 1;
            $scaledQty = $cart->quantity * $parentQty;

            $order->products()->create([
                'quantity' => $scaledQty,
                'price' => 0,
                'purchase_price' => $cart->purchaseItem ? $cart->purchaseItem->purchase_price : $product->purchase_price,
                'sub_total' => 0,
                'discount' => 0,
                'tax_rate' => 0,
                'sale_tax' => 0,
                'customer_tax' => 0,
                'further_tax' => 0,
                'total' => 0,
                'product_id' => $product->id,
                'purchase_item_id' => $cart->purchase_item_id,
                'is_accessory' => true,
                'parent_order_product_id' => $parentOrderProductId,
            ]);

            $product->quantity -= $scaledQty;
            $product->save();

            if ($cart->purchase_item_id && $cart->purchaseItem) {
                $cart->purchaseItem->decrement('remaining_qty', $scaledQty);
            } else {
                // FIFO: consume from oldest batches
                $qtyToConsume = $scaledQty;
                $purchaseItems = PurchaseItem::where('product_id', $product->id)
                    ->where('remaining_qty', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->get();

                $lastProcessedPi = null;
                foreach ($purchaseItems as $pi) {
                    if ($qtyToConsume <= 0)
                        break;
                    $consume = min($pi->remaining_qty, $qtyToConsume);
                    $pi->decrement('remaining_qty', $consume);
                    $qtyToConsume -= $consume;
                    $lastProcessedPi = $pi;
                }

                if ($qtyToConsume > 0) {
                    if ($isStockEnforced) {
                        DB::rollBack();
                        return response()->json([
                            'error' => "Insufficient stock for accessory: {$product->name}"
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
        }

        $total = $totalAmountOrder - $orderDiscount;
        $due = $total - ($request->paid ?? 0);

        $order->update([
            'sub_total' => $totalAmountOrder,
            'discount' => $orderDiscount,
            'total_sale_tax' => round($totalSaleTax, 2),
            'total_customer_tax' => round($totalCustomerTaxOrder, 2),
            'total_further_tax' => round($totalFurtherTax, 2),
            'paid' => $request->paid,
            'total' => round($total, 2),
            'due' => round($due, 2),
            'status' => round($due, 2) <= 0,
            'order_stage' => $order_stage,
            'type' => 'digital_invoice',
        ]);

        if ($order_stage == 'submitted') {

            /* ================= FBR ITEMS (parent products only) ================= */
            $fbrItems = [];
            $furtherTax = 0;
            $lineTotal = 0;
            foreach ($parentCarts as $cart) {
                $product = $cart->product;

                $qty = round($cart->quantity, 2);
                $unitPrice = $cart->price_override ?? $product->discounted_price;
                $lineTotal = round($unitPrice * $qty, 2);

                $rateText = trim($product->rate_of_tax);

                if (str_ends_with($rateText, '%')) {
                    $rate = floatval(str_replace('%', '', $rateText));
                    $valueExTax = $lineTotal;
                    $salesTax = round($valueExTax * ($rate / 100), 2);

                    if ($customer->is_registered && strtolower($customer->is_registered) === 'unregistered' && $product->sale_type != '3rd Schedule Goods') {
                        $furtherTax = round($valueExTax * (4 / 100), 2);
                    } else {
                        $furtherTax = 0;
                    }
                } else {
                    $ratePerUnit = floatval(preg_replace('/[^0-9.]/', '', $rateText));
                    $salesTax = round($ratePerUnit * $qty, 2);
                    $valueExTax = $lineTotal;

                    if ($customer->is_registered && strtolower($customer->is_registered) === 'unregistered' && $product->sale_type != '3rd Schedule Goods') {
                        $furtherTax = round($valueExTax * (4 / 100), 2);
                    } else {
                        $furtherTax = 0;
                    }
                }

                $customFieldsSuffix = $this->formatCustomFieldsForName($cart->custom_fields);

                $fbrItems[] = [
                    "hsCode" => $product->hsCode,
                    "productDescription" => $product->name . $customFieldsSuffix,
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


            $fbrPayload = [
                "buyerRegistrationType" => $buyerRegType,
                "invoiceType" => "Sale Invoice",
                "invoiceDate" => $request->orderDate,
                "sellerNTNCNIC" => $business->ntn,
                "sellerBusinessName" => $business->business_name,
                "sellerProvince" => strtolower($business->state),
                "sellerAddress" => $business->address ?: null,
                "buyerNTNCNIC" => $customer->ntn_cnic,
                "buyerBusinessName" => $customer->name,
                "buyerProvince" => $customer->province,
                "buyerAddress" => $customer->address,
                "invoiceRefNo" => "INV-" . $order->business_id . "-" . str_pad($order->invoice_number, 4, '0', STR_PAD_LEFT),
                "scenarioId" => $SN,
                "items" => $fbrItems
            ];


            if (!$isFbrIntegrationEnabled) {

                DB::commit();

                PosCart::where('user_id', auth()->id())->delete();

                return response()->json([
                    'message' => 'Order completed successfully (FBR Not Integrated)',
                    'order' => $order,
                ], 200);
            } else {

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
                    'payload' => $fbrPayload,
                    'validation_response_digital' => $responseJson,
                ]);

                Log::channel('fbr')->info('FBR Payload', [
                    'order_id' => $order->id ?? null,
                    'user_id' => $userId,
                    'business_id' => $businessId,
                    'payload' => $fbrPayload,
                    'validation_response_digital' => $responseJson,
                ]);

                OrderLog::create([
                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'business_id' => $businessId,
                    'request_payload' => $fbrPayload,
                    'response' => $responseJson,
                    'status_code' => $response->status(),
                    'success' => $response->ok(),
                ]);


                $statusCode = data_get($responseJson, 'validationResponse.statusCode');
                $errorCode = data_get($responseJson, 'validationResponse.errorCode');


                $errorMessage = data_get($responseJson, 'validationResponse.error')
                    ?? data_get($responseJson, 'error');

                /* ================= SAVE API STATUS & LOGS (NO ROLLBACK) ================= */
                Order::where('id', $order->id)->update([
                    'api_status' => $response->status(),
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'api_message' => $response->ok(),
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
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    Log::channel('fbr')->error('FBR Post Connection Exception', [
                        'order_id' => $order->id,
                        'message' => $e->getMessage(),
                    ]);

                    Order::where('id', $order->id)->update([
                        'error_message' => 'Connection error while posting to FBR: ' . $e->getMessage(),
                    ]);

                    return response()->json([
                        'error' => 'Order saved, but posting to FBR failed due to a connection error. It can be retried.',
                        'order' => $order,
                    ], 502);
                }

                $postResponseJson = $post_api_response->json();
                $fbr_invoice_number = data_get($postResponseJson, 'invoiceNumber');
                $postErrorCode = data_get($postResponseJson, 'validationResponse.errorCode') ?? data_get($postResponseJson, 'errorCode');
                $postErrorMessage = data_get($postResponseJson, 'validationResponse.error') ?? data_get($postResponseJson, 'error');

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
                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'business_id' => $businessId,
                    'request_payload' => "POST API Response",
                    'response' => $postResponseJson,
                    'status_code' => $post_api_response->status(),
                    'error_code' => $postErrorCode,
                    'error_message' => $postErrorMessage,
                    'success' => $post_api_response->ok() && !empty($fbr_invoice_number),
                    'fbr_invoice_number' => $fbr_invoice_number,
                ]);

                // ---- THE ACTUAL FIX: check success before declaring victory ----
                if (!$post_api_response->ok() || empty($fbr_invoice_number)) {

                    Order::where('id', $order->id)->update([
                        'error_code' => $postErrorCode,
                        'error_message' => $postErrorMessage ?? 'FBR post call failed with no invoice number returned',
                    ]);

                    PosCart::where('user_id', auth()->id())->delete();

                    return response()->json([
                        'error' => 'Order was validated and saved, but FBR post failed. It will need to be retried.',
                        'error_code' => $postErrorCode,
                        'error_message' => $postErrorMessage,
                        'order' => $order,
                    ], 502);
                }

                // ---- only reached on real success ----
                Order::where('id', $order->id)->update([
                    'fbr_invoice_no' => $fbr_invoice_number,
                ]);

                PosCart::where('user_id', auth()->id())->delete();

                return response()->json([
                    'message' => 'Order completed successfully!',
                    'fbr_payload_sent' => $fbrPayload,
                    'fbr_api_response' => $responseJson,
                    'order' => $order
                ], 200);
            }
        } else {

            DB::commit();

            PosCart::where('user_id', auth()->id())->delete();

            return response()->json([
                'message' => 'Order saved as draft',
                'order' => $order,
            ], 200);

        }
    }

    public function submitDigitalOrder(Request $request, $orderId)
    {
        $businessId = session('business_id');

        if (!$businessId) {
            return response()->json(['error' => 'No business selected'], 422);
        }

        $order = Order::with('products.product', 'products.purchaseItem', 'customer')
            ->where('id', $orderId)
            ->where('business_id', $businessId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($order->is_cancelled) {
            return response()->json(['error' => 'Order is cancelled'], 422);
        }

        if ($order->order_stage === 'submitted') {
            return response()->json(['error' => 'Order already submitted'], 422);
        }

        $customer = Customer::find($order->customer_id);
        if (!$customer) {
            return response()->json(['error' => 'Customer not found for this order'], 422);
        }
        $business = Business::find($businessId);
        if (!$business) {
            return response()->json(['error' => 'Business not found for this order'], 422);
        }

        DB::beginTransaction();

        /* ================= FBR ITEMS (parent products only) ================= */
        $fbrItems = [];

        foreach ($order->products->where('is_accessory', false) as $op) {
            $product = $op->product;

            $qty = round($op->quantity, 2);
            $lineTotal = round($op->price * $qty, 2);
            $rateText = trim($op->tax_rate);
            $salesTax = $op->sale_tax;
            $furtherTax = $op->further_tax;
            $valueExTax = $lineTotal;

            $fbrItems[] = [
                "hsCode" => $product->hsCode,
                "productDescription" => $product->name,
                "rate" => $rateText,
                "uoM" => $product->uom,
                "quantity" => number_format((float) $qty, 2, '.', ''),
                "totalValues" => number_format(($lineTotal + $salesTax + $furtherTax), 2, '.', ''),
                "valueSalesExcludingST" => number_format((float) $valueExTax, 2, '.', ''),
                "fixedNotifiedValueOrRetailPrice" => 0,
                "salesTaxApplicable" => number_format((float) $salesTax, 2, '.', ''),
                "salesTaxWithheldAtSource" => 0,
                "extraTax" => 0,
                "furtherTax" => number_format((float) $furtherTax, 2, '.', ''),
                "sroScheduleNo" => $product->sro_schedule ?: null,
                "fedPayable" => 0,
                "discount" => 0,
                "saleType" => $product->sale_type,
                "sroItemSerialNo" => $product->sro_item ?: null,
            ];
        }

        $buyerRegType = strtolower($customer->is_registered) === 'registered' ? 'Registered' : 'Unregistered';
        $SN = $buyerRegType === 'Registered' ? 'SN001' : 'SN002';

        $fbrPayload = [
            "buyerRegistrationType" => $buyerRegType,
            "invoiceType" => "Sale Invoice",
            "invoiceDate" => $order->order_date->format('Y-m-d'),
            "sellerNTNCNIC" => $business->ntn,
            "sellerBusinessName" => $business->business_name,
            "sellerProvince" => strtolower($business->state),
            "sellerAddress" => $business->address ?: null,
            "buyerNTNCNIC" => $customer->ntn_cnic,
            "buyerBusinessName" => $customer->name,
            "buyerProvince" => $customer->province,
            "buyerAddress" => $customer->address,
            "invoiceRefNo" => "INV-" . $order->business_id . "-" . str_pad($order->invoice_number, 4, '0', STR_PAD_LEFT),
            "scenarioId" => $SN,
            "items" => $fbrItems,
        ];

        $tokens = FbrTokenService::getToken();
        $token = is_array($tokens) ? $tokens['digital_token'] : $tokens;
        $tokenUrlEnd = FbrTokenService::getTokenUrl();
        $invoiceEnvironment = FbrTokenService::getURLEnvironment();

        /* ================= STEP 1: VALIDATE ================= */
        try {
            $response = Http::withOptions([
                'timeout' => 2420,
                'connect_timeout' => 2420,
            ])->withToken($token)
                ->acceptJson()
                ->post("https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata{$tokenUrlEnd}", $fbrPayload);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'FBR validation request failed',
                'message' => $e->getMessage(),
            ], 500);
        }

        if (!$response->successful()) {
            DB::rollBack();
            Log::channel('fbr')->error('FBR validate HTTP error', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $decodedBody = json_decode($response->body(), true);
            return response()->json([
                'error' => 'FBR validation API returned an HTTP error',
                'status' => $response->status(),
                'body' => $decodedBody ?? $response->body(),
            ], 502);
        }


        $body = $response->body();
        $cleanBody = preg_replace('/,\s*([}\]])/', '$1', $body); // fix trailing comma
        $responseJson = json_decode($cleanBody, true);

        Log::info('FBR Payload', [
            'order_id' => $order->id,
            'payload' => $fbrPayload,
            'validation_response_digital' => $responseJson,
        ]);

        Log::channel('fbr')->info('FBR Payload', [
            'order_id' => $order->id,
            'user_id' => auth()->id(),
            'business_id' => $businessId,
            'payload' => $fbrPayload,
            'validation_response_digital' => $responseJson,
        ]);

        $statusCode = data_get($responseJson, 'validationResponse.statusCode');
        $errorCode = data_get($responseJson, 'validationResponse.errorCode');
        $errorMessage = data_get($responseJson, 'validationResponse.error') ?? data_get($responseJson, 'error');

        OrderLog::create([
            'order_id' => $order->id,
            'user_id' => auth()->id(),
            'business_id' => $businessId,
            'request_payload' => $fbrPayload,
            'response' => $responseJson,
            'status_code' => $response->status(),
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'success' => $response->ok(),
        ]);

        if ($statusCode != "00") {
            DB::rollBack();

            $order->update([
                'api_status' => $response->status(),
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'api_message' => 0,
            ]);

            return response()->json([
                'error' => 'FBR validation failed',
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'fbr_api_response' => $responseJson,
            ], 422);
        }

        /* ================= STEP 2: POST ================= */
        try {
            $post_api_response = Http::withOptions([
                'timeout' => 2420,
                'connect_timeout' => 2420,
            ])->withToken($token)
                ->acceptJson()
                ->post("https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata{$tokenUrlEnd}", $fbrPayload);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'FBR post request failed',
                'message' => $e->getMessage(),
            ], 500);
        }

        $postResponseJson = $post_api_response->json();
        $fbr_invoice_number = data_get($postResponseJson, 'invoiceNumber');
        $postErrorCode = data_get($postResponseJson, 'validationResponse.errorCode') ?? data_get($postResponseJson, 'errorCode');
        $postErrorMessage = data_get($postResponseJson, 'validationResponse.error') ?? data_get($postResponseJson, 'error');

        if (!$post_api_response->successful() || empty($fbr_invoice_number)) {

            DB::rollBack();

            Log::channel('fbr')->error('FBR post failed', [
                'order_id' => $order->id,
                'status' => $post_api_response->status(),
                'body' => $post_api_response->body(),
            ]);

            OrderLog::create([
                'order_id' => $order->id,
                'user_id' => auth()->id(),
                'business_id' => $businessId,
                'request_payload' => "POST API Response",
                'response' => $postResponseJson,
                'status_code' => $post_api_response->status(),
                'error_code' => $postErrorCode,
                'error_message' => $postErrorMessage,
                'success' => false,
            ]);

            return response()->json([
                'error' => 'FBR post failed after successful validation',
                'error_code' => $postErrorCode,
                'error_message' => $postErrorMessage,
                'fbr_api_response' => $postResponseJson,
            ], 502);
        }

        $order->update([
            'api_status' => $response->status(),
            'error_code' => null,
            'error_message' => null,
            'api_message' => 1,
            'fbr_invoice_no' => $fbr_invoice_number,
            'order_stage' => 'submitted',
        ]);

        DB::commit();

        Log::info('FBR Payload', [
            'order_id' => $order->id,
            'post_response_digital' => $postResponseJson,
        ]);

        Log::channel('fbr')->info('FBR Payload', [
            'order_id' => $order->id,
            'user_id' => auth()->id(),
            'business_id' => $businessId,
            'post_response_digital' => $postResponseJson,
            'fbr_invoice_number' => $fbr_invoice_number,
        ]);

        OrderLog::create([
            'order_id' => $order->id,
            'user_id' => auth()->id(),
            'business_id' => $businessId,
            'request_payload' => "POST API Response",
            'response' => $postResponseJson,
            'status_code' => $response->status(),
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'success' => $response->ok(),
            'fbr_invoice_number' => $fbr_invoice_number,
        ]);

        return response()->json([
            'message' => 'Order completed successfully!',
            'fbr_payload_sent' => $fbrPayload,
            'fbr_api_response' => $responseJson,
            'order' => $order,
        ], 200);
    }

    public function cancelOrder(Request $request)
    {
        $request->validate([
            'order_id' => ['required', 'exists:orders,id', 'integer'],
        ]);

        $businessId = session('business_id');

        if (!$businessId) {
            return response()->json(['error' => 'No business selected'], 422);
        }

        $order = Order::with('products.product', 'products.purchaseItem')
            ->where('id', $request->order_id)
            ->where('business_id', $businessId)
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        if ($order->is_cancelled) {
            return response()->json(['error' => 'Order is already cancelled'], 422);
        }

        DB::beginTransaction();

        try {
            foreach ($order->products as $op) {
                $product = $op->product;

                if ($product) {
                    $product->quantity += $op->quantity;
                    $product->save();
                }

                if ($op->purchase_item_id && $op->purchaseItem) {
                    $op->purchaseItem->increment('remaining_qty', $op->quantity);
                } else {
                    // FIFO cancel: restore quantity to batches, latest first
                    $qtyToRestore = $op->quantity;
                    $batches = PurchaseItem::where('product_id', $product->id)
                        ->orderBy('created_at', 'desc')
                        ->get();

                    foreach ($batches as $batch) {
                        if ($qtyToRestore <= 0)
                            break;
                        $availableSpace = $batch->quantity - $batch->remaining_qty;
                        if ($availableSpace > 0) {
                            $restore = min($qtyToRestore, $availableSpace);
                            $batch->increment('remaining_qty', $restore);
                            $qtyToRestore -= $restore;
                        }
                    }

                    if ($qtyToRestore > 0) {
                        $latestBatch = $batches->first();
                        if ($latestBatch) {
                            $latestBatch->increment('remaining_qty', $qtyToRestore);
                        }
                    }
                }
            }

            $order->update([
                'is_cancelled' => true,
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Order cancelled and stock restored successfully!',
                'order' => $order->fresh('products'),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order Cancel Error: ' . $e->getMessage());
            return response()->json(['error' => 'Something went wrong while cancelling the order'], 500);
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
        $businessId = session('business_id');

        $order = Order::with([
            'customer',
            'products.product',
            'business.invoiceSetting',
        ])->findOrFail($id);
        $business = Business::findOrFail($businessId);
        $fbrIntegration = FbrTokenService::isIntegrationEnabled();
        $invoiceSettings = InvoiceSetting::where('business_id', $businessId)->first();

        $allFields = BusinessCustomField::where('business_id', $order->business_id)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get();

        // Keep only fields that have a non-empty value in at least one order item
        $customFields = $allFields->filter(function ($field) use ($order) {
            return $order->products->contains(function ($item) use ($field) {
                $val = $item->custom_fields[$field->field_name] ?? null;
                return $val !== null && $val !== '';
            });
        })->values();

        return view('backend.orders.print-invoice', compact('order', 'business', 'fbrIntegration', 'invoiceSettings', 'customFields'));
    }

    public function downloadInvoicesPdf(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return back()->with('error', 'No orders selected.');
        }

        $businessId = session('business_id');
        $business = Business::findOrFail($businessId);
        $fbrIntegration = FbrTokenService::isIntegrationEnabled();
        $invoiceSettings = InvoiceSetting::where('business_id', $businessId)->first();

        $orders = Order::with(['customer', 'products.product'])
            ->whereIn('id', $ids)
            ->where('business_id', $businessId)
            ->orderBy('id', 'desc')
            ->get();

        $allFields = BusinessCustomField::where('business_id', $businessId)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get();

        // ---- Helper: reliably embed any local image as base64 ----
        $fileToBase64 = function (?string $absolutePath): ?string {
            if (!$absolutePath || !file_exists($absolutePath)) {
                return null;
            }
            $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'svg' => 'image/svg+xml',
                default => 'image/png',
            };
            return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($absolutePath));
        };

        // Business logo
        $logoBase64 = null;
        if (readConfig('is_show_logo_invoice')) {
            $logoPath = !empty($business->logo)
                ? storage_path('app/public/' . $business->logo)
                : public_path('storage/logos/default-business.png');
            $logoBase64 = $fileToBase64($logoPath);
        }

        // The 3 possible FBR/PRAL/POS icons — precompute once each
        $pralImage = $fileToBase64(public_path('assets/images/pral-pos-component.png'));
        $posImage = $fileToBase64(public_path('assets/images/fbr_pos_invoice_system.png'));
        $digitalImage = $fileToBase64(public_path('assets/images/fbr_logo-icon.png'));

        // IMC logo
        $imcLogoBase64 = (!empty($invoiceSettings->imc_logo) && $invoiceSettings->imc_logo == 1)
            ? $fileToBase64(public_path('assets/images/imc.jpeg'))
            : null;

        $invoices = $orders->map(function ($order) use ($allFields, $fbrIntegration, $business, $pralImage, $posImage, $digitalImage) {

            $customFields = $allFields->filter(function ($field) use ($order) {
                return $order->products->contains(function ($item) use ($field) {
                    $val = $item->custom_fields[$field->field_name] ?? null;
                    return $val !== null && $val !== '';
                });
            })->values();

            $qrCode = null;
            if ($fbrIntegration == 'enabled' && $order->order_stage !== 'draft' && filled($order->fbr_invoice_no)) {
                $qrCode = base64_encode(
                    QrCode::format('svg')->size(60)->generate($order->fbr_invoice_no)
                );
            }

            // Pick the right icon for this order, same logic as the single invoice view
            $fbrImage = $business->business_type == 'pral'
                ? $pralImage
                : ($order->type == 'pos_invoice' ? $posImage : $digitalImage);

            return [
                'order' => $order,
                'customFields' => $customFields,
                'qrCode' => $qrCode,
                'fbrImage' => $fbrImage,
            ];
        });

        $pdf = Pdf::loadView('backend.orders.print-invoice-multiple', compact(
            'invoices',
            'business',
            'fbrIntegration',
            'invoiceSettings',
            'logoBase64',
            'imcLogoBase64'
        ))->setPaper('a4', 'portrait')
            ->setOptions(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);

        return $pdf->download('Invoices - ' . readConfig('site_name') . '.pdf');
    }

    public function customerTaxInvoice($id)
    {
        $businessId = session('business_id');

        $order = Order::with([
            'customer',
            'products.product',
            'business.invoiceSetting',
        ])->findOrFail($id);
        $business = Business::findOrFail($businessId);
        $fbrIntegration = FbrTokenService::isIntegrationEnabled();
        $invoiceSettings = InvoiceSetting::where('business_id', $businessId)->first();

        return view('backend.orders.customer-tax-invoice.print-invoice', compact('order', 'business', 'fbrIntegration', 'invoiceSettings'));
    }

    public function customerTaxReceiptDigital($id)
    {
        $businessId = session('business_id');

        $order = Order::with(['customer', 'products.product'])
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $invoiceSettings = InvoiceSetting::where('business_id', $businessId)->first();

        $business = Business::findOrFail($businessId);

        $maxWidth = $invoiceSettings->post_invoice_width ?? '300px';

        $fbrIntegration = FbrTokenService::isIntegrationEnabled();

        return view('backend.orders.customer-tax-invoice.pos-invoice-digital', compact('order', 'maxWidth', 'invoiceSettings', 'business', 'fbrIntegration'));
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
            $order->due = round((float) $due, 2);
            $order->paid = round((float) $paid, 2);
            $order->status = round((float) $due, 2) <= 0;
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

    public function posInvoice($id)
    {
        $businessId = session('business_id');

        $order = Order::with(['customer', 'products.product'])
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $invoiceSettings = InvoiceSetting::where('business_id', $businessId)->first();

        $business = Business::findOrFail($businessId);

        $maxWidth = $invoiceSettings->post_invoice_width ?? '300px';

        $fbrIntegration = FbrTokenService::isIntegrationEnabled();

        return view('backend.orders.pos-invoice', compact('order', 'maxWidth', 'invoiceSettings', 'business', 'fbrIntegration'));
    }


    public function posInvoiceDigital($id)
    {
        $businessId = session('business_id');

        $order = Order::with(['customer', 'products.product'])
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $invoiceSettings = InvoiceSetting::where('business_id', $businessId)->first();

        $business = Business::findOrFail($businessId);

        $maxWidth = $invoiceSettings->post_invoice_width ?? '300px';

        $fbrIntegration = FbrTokenService::isIntegrationEnabled();

        return view('backend.orders.pos-invoice-digital', compact('order', 'maxWidth', 'invoiceSettings', 'business', 'fbrIntegration'));
    }

    public function fbrStatus($id)
    {
        $data = Order::find($id);

        if (!$data) {
            return response()->json([
                'message' => "Order not found",
                'fbr_invoice_no' => null
            ]);
        }

        if ($data->api_status != 200) {

            $message = "API Not Run Due to Some Issue From FBR";

        } elseif (
            $data->api_status == 200 &&
            empty($data->error_code) &&
            $data->fbr_invoice_no != null
        ) {

            $message = "Successfully Sent to FBR.";

        } else {

            $message = "Failed to send to FBR " . $data->error_code;

            if (!empty($data->error_message)) {
                $message .= " - " . $data->error_message;
            }
        }

        return response()->json([
            'message' => $message,
            'fbr_invoice_no' => $data->fbr_invoice_no
        ]);
    }

    private function validateCustomInvoiceNumber(
        Request $request,
        ?Order $order = null
    ): array {
        $businessId = session('business_id');

        $uniqueRule = Rule::unique('orders', 'custom_invoice_number')
            ->where('business_id', $businessId);

        // When updating an existing order
        if ($order) {
            $uniqueRule->ignore($order->id);
        }

        return $request->validate([
            'custom_invoice_number' => [
                'nullable',
                'string',
                'max:100',
                $uniqueRule,
            ],
        ]);
    }

    private function formatCustomFieldsForName($customFields): string
    {
        if (empty($customFields) || !is_array($customFields)) {
            return '';
        }

        $parts = [];
        foreach ($customFields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = ucfirst($key) . ': ' . $value;
        }

        return $parts ? ' (' . implode(', ', $parts) . ')' : '';
    }

    // public function updateCustomInvoice(Request $request, Order $order)
    // {
    //     $businessId = session('business_id');

    //     abort_if($order->business_id != $businessId, 403);

    //     $request->validate([
    //         'custom_invoice_number' => [
    //             'nullable',
    //             'string',
    //             'max:100',
    //             Rule::unique('orders', 'custom_invoice_number')
    //                 ->where('business_id', $businessId)
    //                 ->ignore($order->id),
    //         ],
    //     ]);

    //     $order->update([
    //         'custom_invoice_number' => $request->custom_invoice_number,
    //     ]);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Custom invoice number updated successfully.',
    //     ]);
    // }
}