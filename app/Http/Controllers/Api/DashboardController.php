<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;


class DashboardController extends Controller
{
    public function index()
    {
        $business = currentUserBusinessApi();

        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or unauthorized business'
            ], 403);
        }

        $businessId = $business->id;

        $orders = Order::where('business_id', $businessId)->get();

        $customers = Customer::where('business_id', $businessId);

        $products = Product::where('business_id', $businessId);

        $orderProducts = OrderProduct::whereHas('order', function ($q) use ($businessId) {
            $q->where('business_id', $businessId);
        });

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

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }
}
