<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderTransaction;
use App\Models\Product;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Get the current business ID from session
        $businessId = session('business_id');

        $orders = Order::when($businessId, function($query) use ($businessId) {
            return $query->where('business_id', $businessId);
        })->get();

        $customers = Customer::when($businessId, function($query) use ($businessId) {
            return $query->where('business_id', $businessId);
        });
        
        $products = Product::when($businessId, function($query) use ($businessId) {
            return $query->where('business_id', $businessId);
        });

        $orderProducts = OrderProduct::when($businessId, function($query) use ($businessId) {
            return $query->whereHas('order', function($q) use ($businessId) {
                $q->where('business_id', $businessId);
            });
        });

        // Calculate totals
        $data = [
            'sub_total' => $orders->sum('sub_total'),
            'discount' => $orders->sum('discount'),
            'total' => $orders->sum('total'),
            'paid' => $orders->sum('paid'),
            'due' => $orders->sum('due'),
            'total_customer' => $customers->count(),
            'total_order' => $orders->count(),
            'total_product' => $products->count(),
            'total_sale_item' => $orderProducts->sum('quantity'),
        ];

        // Debugging ke liye
        Log::info('Dashboard accessed for business: ' . $businessId);
        Log::info('Total orders: ' . $orders->count());

        // Daily Sales Chart Data - FIXED VERSION
        $startDate = Carbon::now()->subDays(30)->startOfDay();
        $endDate = Carbon::now()->endOfDay();
        
        if($request->has('daterange')) {
            $dates = explode(' to ', $request->query('daterange'));

            if (count($dates) == 2) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
            }
        }

        Log::info('Date range for daily sales: ' . $startDate . ' to ' . $endDate);

        // Get daily totals with proper date handling
        $dailyTotals = Order::when($businessId, function($query) use ($businessId) {
                return $query->where('business_id', $businessId);
            })
            ->selectRaw('DATE(created_at) as date, SUM(total) as total_amount')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        Log::info('Daily totals found: ' . $dailyTotals->count());
        Log::info('Daily totals data: ' . $dailyTotals->toJson());

        // Agar koi data nahi hai toh empty arrays return karein
        if ($dailyTotals->isEmpty()) {
            $dates = [];
            $totalAmounts = [];
            
            // Last 30 days ke dates generate karein
            for ($i = 29; $i >= 0; $i--) {
                $dates[] = Carbon::now()->subDays($i)->format('Y-m-d');
                $totalAmounts[] = 0;
            }
        } else {
            $dates = $dailyTotals->pluck('date')->map(function($date) {
                return Carbon::parse($date)->format('m/d/Y'); // Chart ke liye proper format
            })->toArray();
            
            $totalAmounts = $dailyTotals->pluck('total_amount')->toArray();
        }

        $data['dates'] = $dates;
        $data['totalAmounts'] = $totalAmounts;
        $data['dateRange'] = 'from '. $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d');

        // Monthly Sales Chart Data - FIXED VERSION
        $currentYear = now()->year;
        $data['currentYear'] = $currentYear;

        $salesData = Order::when($businessId, function($query) use ($businessId) {
                return $query->where('business_id', $businessId);
            })
            ->selectRaw('MONTH(created_at) as month, SUM(total) as total_amount')
            ->whereYear('created_at', $currentYear)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->pluck('total_amount', 'month')
            ->toArray();


        Log::info('Monthly sales data: ' . json_encode($salesData));

        $monthNames = [];
        $tempTotalAmountMonth = [];
        
        for ($i = 1; $i <= 12; $i++) {
            $monthNames[] = Carbon::create()->month($i)->format('F'); // Month names
            $tempTotalAmountMonth[] = $salesData[$i] ?? 0;
        }

        $data['months'] = $monthNames; // Month names use karein
        $data['totalAmountMonth'] = $tempTotalAmountMonth;

        // Final data log karein
        Log::info('Final chart data - Dates: ' . json_encode($dates));
        Log::info('Final chart data - Amounts: ' . json_encode($totalAmounts));
        Log::info('Final monthly data - Months: ' . json_encode($monthNames));
        Log::info('Final monthly data - Amounts: ' . json_encode($tempTotalAmountMonth));

        return view('backend.index', $data);
    }

    public function profile()
    {
        $user = auth()->user();
        return view('backend.profile.index', compact('user'));
    }
}
