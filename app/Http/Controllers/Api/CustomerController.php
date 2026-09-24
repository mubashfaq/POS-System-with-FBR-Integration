<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Http\Resources\CustomerResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $business = $request->attributes->get('business');

        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business context required.'
            ], 400);
        }

        $query = Customer::where('business_id', $business->id);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('address', 'LIKE', "%{$search}%");
            });
        }

        $customers = $query->latest()->get();

        return response()->json([
            'message' => 'Customers retrieved successfully',
            'data' => $customers
        ]);
    }
    
    
    public function businessCustomers(Request $request)
    {
        $business_id = $request->query('business_id');

        if (!$business_id) {
            return response()->json([
                'status' => false,
                'message' => 'Business context required.'
            ], 400);
        }

        $query = Customer::where('business_id', $business_id);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('address', 'LIKE', "%{$search}%");
            });
        }

        $customers = $query->latest()->get();

        return response()->json([
            'message' => 'Customers retrieved successfully',
            'data' => $customers
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $business = $request->attributes->get('business');

        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business context required.'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('customers')->where(function ($query) use ($business) {
                    return $query->where('business_id', $business->id);
                }),
            ],
            'ntn_cnic' => [
                'required',
                'string',
                Rule::unique('customers')->where(function ($query) use ($business) {
                    return $query->where('business_id', $business->id);
                }),
            ],
            'province' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'is_registered' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $validated['business_id'] = $business->id;

        $customer = Customer::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Customer created successfully!',
            'data' => $customer
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        $business = $request->attributes->get('business');
        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business context required.'
            ], 400);
        }

        $customer = Customer::where('business_id', $business->id)->find($id);

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.'
            ], 404);
        }

        return response()->json([
            'message' => 'Customer retrieved successfully',
            'data' => $customer
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $business = $request->attributes->get('business');

        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business context required.'
            ], 400);
        }

        $customer = Customer::where('business_id', $business->id)->find($id);

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('customers')->where(function ($query) use ($business) {
                    return $query->where('business_id', $business->id);
                })->ignore($customer->id),
            ],
            'ntn_cnic' => 'required|string',
            'province' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'is_registered' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $customer->update($validator->validated());

        return response()->json([
            'status' => true,
            'message' => 'Customer updated successfully!',
            'data' => $customer
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $business = $request->attributes->get('business');
        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business context required.'
            ], 400);
        }

        $customer = Customer::where('business_id', $business->id)->find($id);

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.'
            ], 404);
        }

        $customer->delete();

        return response()->json([
            'status' => true,
            'message' => 'Customer deleted successfully',
        ], 200);
    }
}
