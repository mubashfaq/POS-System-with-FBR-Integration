<?php

namespace App\Http\Controllers\Backend\Report;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{

    public function saleReport(Request $request)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('reports_sales'), 403);

        $businessId = session('business_id');

        $start_date_input = $request->input('start_date', Carbon::today()->subDays(29)->format('Y-m-d'));
        $end_date_input = $request->input('end_date', Carbon::today()->format('Y-m-d'));
        $customerId = $request->input('customer_id');

        $start_date = Carbon::createFromFormat('Y-m-d', $start_date_input)->startOfDay();
        $end_date = Carbon::createFromFormat('Y-m-d', $end_date_input)->endOfDay();

        $environment = $request->input('environment');
        $dateField = $request->input('date_field', 'created_at');

        $isSuperAdmin = auth()->user()->hasRole('super_admin');

        // Only allow valid date fields
        if (!in_array($dateField, ['created_at', 'order_date'])) {
            $dateField = 'created_at';
        }

        // Only super admin can use environment filter
        if (!$isSuperAdmin) {
            $environment = null;
        }

        // ── Fetch business once (avoids repeated DB hits for business_type) ──
        $business = \App\Models\Business::select('id', 'business_type')->find($businessId);

        // ── Single optimized query — no N+1 ─────────────────────────────────
        // 'products'         → OrderProduct (pivot/intermediate model)
        // 'products.product' → the actual Product model via OrderProduct
        $orders = Order::where('business_id', $businessId)
            ->when(
                $isSuperAdmin && $environment,
                fn($q) => $q->where('invoice_environment', $environment)
            )
            ->whereNotNull($dateField)
            ->whereBetween($dateField, [$start_date, $end_date])
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->with([
                'customer:id,name,is_registered',

                'products' => function ($q) {
                    $q->where('order_products.is_accessory', 0);
                },

                'products.product:id,name,purchase_price,rate_of_tax,pos_sale_tax,sale_type',
            ])
            ->orderBy($dateField)
            ->get();

        $orders->each(function ($order) {

            // ── Tax calculation ──────────────────────────────────────────────
            $orderSalesTax = 0;
            $orderFurtherTax = 0;
            $customer = $order->customer;
            $isPosInvoice = $order->type === 'pos_invoice';

            foreach ($order->products as $item) {
                $taxRate = $isPosInvoice ? $item->product->pos_sale_tax : $item->product->rate_of_tax;
                $taxRate = floatval(str_replace('%', '', $taxRate));
                $exclTax = $item->sub_total;
                $taxAmount = round($exclTax * ($taxRate / 100), 2);

                $furtherTax = 0;
                if (
                    !$isPosInvoice && $customer && strtolower($customer->is_registered ?? '') === 'unregistered' &&
                    $item->product->sale_type !== '3rd Schedule Goods'
                ) {
                    $furtherTax = round($exclTax * 0.04, 2);
                }

                $orderSalesTax += $taxAmount;
                $orderFurtherTax += $furtherTax;
            }

            $order->order_sales_tax = round($orderSalesTax, 2);
            $order->order_further_tax = round($orderFurtherTax, 2);

            $order->sale_tax_final = $order->total_sale_tax ?? $order->order_sales_tax;
            $order->further_tax_final = $order->total_further_tax ?? $order->order_further_tax;

            $saleTax = $order->sale_tax_final;
            $furtherTax = $order->further_tax_final;

            // ── Cost / profit ────────────────────────────────────────────────
            $order->order_cost = $order->products->sum(
                fn($item) => $item->product->purchase_price * $item->quantity
            );
            $orderProfit = $order->total - $saleTax - $furtherTax - $order->order_cost;
            $order->order_profit = $orderProfit > 0 ? $orderProfit : 0;
            $order->order_loss = $orderProfit < 0 ? abs($orderProfit) : 0;
        });

        $data = [
            'orders' => $orders,
            'business' => $business,
            'sub_total' => $orders->sum('sub_total'),
            'discount' => $orders->sum('discount'),
            'paid' => $orders->sum('paid'),
            'due' => $orders->sum('due'),
            'total' => $orders->sum('total'),
            'sales_tax' => $orders->sum('sale_tax_final'),
            'further_tax' => $orders->sum('further_tax_final'),
            'start_date' => $start_date->format('M d, Y'),
            'end_date' => $end_date->format('M d, Y'),
            'profit' => $orders->sum('order_profit'),
            'loss' => $orders->sum('order_loss'),
            'total_cost' => $orders->sum('order_cost'),
            'customer' => $customerId ? \App\Models\Customer::select('id', 'name', 'address', 'phone')->find($customerId) : null,
            'date_field' => $dateField,
            'environment' => $environment,
            'isSuperAdmin' => $isSuperAdmin,
        ];

        return view('backend.reports.sale-report', $data);
    }

    public function saleSummery(Request $request)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('reports_summary'), 403);

        // abort_if(!auth()->user()->can('reports_summary'), 403);
        // Get user input or set default values

        $businessId = session('business_id');

        if (!$businessId) {
            return redirect()->route('business.select')
                ->with('error', 'Please select a business first.');
        }

        $start_date_input = $request->input('start_date', Carbon::today()->subDays(29)->format('Y-m-d'));
        $end_date_input = $request->input('end_date', Carbon::today()->format('Y-m-d'));

        // Parse and set start date
        $start_date = Carbon::createFromFormat('Y-m-d', $start_date_input) ?: Carbon::today()->subDays(29)->startOfDay();
        $start_date = $start_date->startOfDay();

        // Parse and set end date
        $end_date = Carbon::createFromFormat('Y-m-d', $end_date_input) ?: Carbon::today()->endOfDay();
        $end_date = $end_date->endOfDay();
        // Retrieve orders within the date range
        $orders = Order::where('business_id', $businessId)
            ->whereBetween('created_at', [$start_date, $end_date])
            ->get();

        // Calculate totals
        $data = [
            'sub_total' => $orders->sum('sub_total'),
            'discount' => $orders->sum('discount'),
            'paid' => $orders->sum('paid'),
            'due' => $orders->sum('due'),
            'total' => $orders->sum('total'),
            'start_date' => $start_date->format('M d, Y'),
            'end_date' => $end_date->format('M d, Y'),
        ];

        return view('backend.reports.sale-summery', $data);
    }
    function inventoryReport(Request $request)
    {

        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('reports_inventory'), 403);

        // abort_if(!auth()->user()->can('reports_inventory'), 403);
        $businessId = session('business_id');

        if ($request->ajax()) {
            // $products = Product::select('products.* , sum(purchase_items.purchase_price) as total_purchase_price , sum(purchase_items.quantity) as purchase_quantity')
            // ->where('business_id', $businessId) 
            // ->leftJoin('purchase_items', 'products.id', '=', 'purchase_items.product_id')
            // ->latest()
            // ->active()
            // ->get();
            $products = Product::where('business_id', $businessId)
                ->active()
                ->with([
                    'purchaseItems' => function ($query) {
                        $query->select('id', 'product_id', 'purchase_price', 'quantity');
                    }
                ])
                ->latest()
                ->get();

            $products->each(function ($product) {
                $product->total_purchase_price = $product->purchaseItems->sum('purchase_price');
                $product->purchase_quantity = $product->purchaseItems->sum('quantity');
            });


            return DataTables::of($products)
                ->addIndexColumn()
                ->addColumn('name', fn($data) => $data->name)
                ->addColumn('sku', fn($data) => $data->sku)
                ->addColumn(
                    'price',
                    fn($data) => $data->discounted_price .
                        ($data->price > $data->discounted_price
                            ? '<br><del>' . $data->price . '</del>'
                            : '')
                )
                ->addColumn('quantity', fn($data) => $data->quantity . ' ' . optional($data->unit)->short_name)
                ->addColumn('total_value', fn($data) => $data->total_purchase_price / ($data->purchase_quantity ?: 1) * $data->quantity)

                ->rawColumns(['name', 'sku', 'price', 'quantity', 'status', 'total_value'])
                ->toJson();
        }
        return view('backend.reports.inventory');
    }
}
