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

        // abort_if(!auth()->user()->can(abilities: 'reports_sales'), 403);
        // Get user input or set default values

        $businessId = session('business_id');

        $start_date_input = $request->input('start_date', Carbon::today()->subDays(29)->format('Y-m-d'));
        $end_date_input = $request->input('end_date', Carbon::today()->format('Y-m-d'));
        $customerId = $request->input('customer_id');

        // Parse and set start date
        $start_date = Carbon::createFromFormat('Y-m-d', $start_date_input) ?: Carbon::today()->subDays(29)->startOfDay();
        $start_date = $start_date->startOfDay();

        // Parse and set end date
        $end_date = Carbon::createFromFormat('Y-m-d', $end_date_input) ?: Carbon::today()->endOfDay();
        $end_date = $end_date->endOfDay();
        // Retrieve orders within the date range with products for profit calculation
        $orders = Order::where('business_id', $businessId)
        ->whereBetween('created_at', [$start_date, $end_date])
        ->when($customerId, function ($query) use ($customerId) {
            return $query->where('customer_id', $customerId);
        })
        ->with(['customer', 'products'])
        ->get();

        // Calculate profit/loss per order
        $orders->each(function ($order) {
            $order->order_purchase_price = $order->products->sum('purchase_price');
            $order->order_cost = $order->products->sum(function ($product) {
                return $product->purchase_price * $product->quantity;
            });
            $orderProfit = $order->total - $order->order_cost;
            $order->order_profit = $orderProfit > 0 ? $orderProfit : 0;
            $order->order_loss = $orderProfit < 0 ? abs($orderProfit) : 0;
        });

        // Aggregate totals
        $total_profit = $orders->sum('order_profit');
        $total_loss = $orders->sum('order_loss');

        // Calculate totals
        $data = [
            'orders' => $orders,
            'sub_total' => $orders->sum('sub_total'),
            'discount' => $orders->sum('discount'),
            'paid' => $orders->sum('paid'),
            'due' => $orders->sum('due'),
            'total' => $orders->sum('total'),
            'start_date' => $start_date->format('M d, Y'),
            'end_date' => $end_date->format('M d, Y'),
            'profit' => $total_profit,
            'loss' => $total_loss,
            'total_cost' => $orders->sum('order_cost'), 
            'customer' => $customerId ? \App\Models\Customer::find($customerId) : null,
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
                ->with(['purchaseItems' => function ($query) {
                    $query->select('id', 'product_id', 'purchase_price', 'quantity');
                }])
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
               
                ->rawColumns(['name', 'sku', 'price', 'quantity', 'status' , 'total_value' ])
                ->toJson();
        }
        return view('backend.reports.inventory');
    }
}
