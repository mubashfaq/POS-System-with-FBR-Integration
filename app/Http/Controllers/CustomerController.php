<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\FbrTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;
use Illuminate\Validation\Rule;


class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('customer_view'), 403);

        // abort_if(!auth()->user()->can('customer_view'), 403);

        $businessId = session('business_id');

        if ($request->ajax()) {
            $customers = Customer::where('business_id', $businessId)
                ->latest()
                ->get();
            return DataTables::of($customers)
                ->addIndexColumn()
                ->addColumn('name', fn($data) => $data->name)
                ->addColumn('phone', fn($data) => $data->phone)
                ->addColumn('address', fn($data) => $data->address)
                ->addColumn('created_at', fn($data) => $data->created_at->format('d M, Y')) // Using Carbon for formatting
                ->addColumn('action', function ($data) {
                    $actionHtml = '<div class="btn-group">
        <button type="button" class="btn btn-flat">Action</button>
        <button type="button" class="btn btn-flat dropdown-toggle dropdown-icon" data-toggle="dropdown" aria-expanded="false">
            <span class="sr-only">Toggle Dropdown</span>
        </button>
        <div class="dropdown-menu" role="menu">';

                    // Check if the user has permission to update customers
                    if (auth()->user()->can('customer_update')) {
                        $actionHtml .= '<a class="dropdown-item" href="' . route('backend.admin.customers.edit', $data->id) . '" ' . ($data->id == 1 ? 'onclick="event.preventDefault();"' : '') . '>
            <i class="fas fa-edit"></i> Edit
        </a>';
                        $actionHtml .= '<div class="dropdown-divider"></div>';
                    }

                    // Check if the user has permission to delete customers
                    if (auth()->user()->can('customer_delete')) {
                        $actionHtml .= '<form action="' . route('backend.admin.customers.destroy', $data->id) . '" method="POST" style="display:inline;">
            ' . csrf_field() . '
            ' . method_field("DELETE") . '
            <button type="submit" ' . ($data->id == 1 ? 'disabled' : '') . ' class="dropdown-item" onclick="return confirm(\'Are you sure?\')">
                <i class="fas fa-trash"></i> Delete
            </button>
        </form>';
                        $actionHtml .= '<div class="dropdown-divider"></div>';
                    }

                    if (auth()->user()->can('customer_sales')) {
                        $actionHtml .= '<a class="dropdown-item" href="' . route('backend.admin.customers.orders', $data->id) . '">
        <i class="fas fa-cart-plus"></i> Sales
    </a>';
                    }

                    $actionHtml .= '</div></div>';
                    return $actionHtml;
                })

                ->rawColumns(['name', 'phone', 'address', 'created_at', 'action'])
                ->toJson();
        }


        return view('backend.customers.index', compact('currentBusiness'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('customer_create'), 403);

        // abort_if(!auth()->user()->can('customer_create'), 403);
        return view('backend.customers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $currentBusiness = currentUserBusiness();

        $businessId = session('business_id');

        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('customer_create'), 403);

        // abort_if(!auth()->user()->can('customer_create'), 403);
        if ($request->wantsJson()) {
            $request->validate([
                'name' => 'required|string',
                'phone' => [
                    'nullable',
                    'string',
                    'max:20',
                    Rule::unique('customers')->where(function ($query) use ($businessId) {
                        return $query->where('business_id', $businessId);
                    }),
                ],
                'ntn_cnic' => [
                    'nullable',
                    'string',
                    'regex:/^[0-9]+$/', // only digits allowed
                    Rule::unique('customers')->where(function ($query) use ($businessId) {
                        return $query->where('business_id', $businessId);
                    }),
                ],
                'province' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:255',
                'is_registered' => 'nullable|string|max:255',
            ]);

            $customer = Customer::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'ntn_cnic' => $request->ntn_cnic,
                'province' => $request->province,
                'address' => $request->address,
                'is_registered' => $request->is_registered ?? 'Unregistered',
                'business_id' => $businessId
            ]);

            return response()->json($customer);
        }
        $request->validate([
            'name' => 'required|string|max:255',
            // 'phone' => 'required|string|max:20|unique:customers,phone',
            // 'ntn_cnic' => 'required|integer|unique:customers,ntn_cnic',
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('customers')->where(function ($query) use ($businessId) {
                    return $query->where('business_id', $businessId);
                }),
            ],
            'ntn_cnic' => [
                'required',
                'integer',
                Rule::unique('customers')->where(function ($query) use ($businessId) {
                    return $query->where('business_id', $businessId);
                }),
            ],
            'province' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'is_registered' => 'required|string|max:255',

        ]);

        if (!$businessId) {
            return redirect()->back()->with('error', 'No business selected.');
        }


        $customer = Customer::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'ntn_cnic' => $request->ntn_cnic,
            'province' => $request->province,
            'address' => $request->address,
            'is_registered' => $request->is_registered,
            'business_id' => $businessId,
        ]);

        session()->flash('success', 'Customer created successfully.');
        return to_route('backend.admin.customers.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('customer_update'), 403);


        // abort_if(!auth()->user()->can('customer_update'), 403);
        $customer = Customer::findOrFail($id);
        return view('backend.customers.edit', compact('customer'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('customer_update'), 403);


        // abort_if(!auth()->user()->can('customer_update'), 403);
        $customer = Customer::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:customers,phone,' . $customer->id, // Corrected syntax
            'ntn_cnic' => 'required|string|regex:/^[0-9]+$/',
            'province' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'is_registered' => 'required|string|max:255',
        ]);

        $customer->update($request->only(['name', 'phone', 'ntn_cnic', 'province', 'address', 'is_registered']));

        session()->flash('success', 'Customer updated successfully.');
        return to_route('backend.admin.customers.index');
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('customer_delete'), 403);

        // abort_if(!auth()->user()->can('customer_delete'), 403);
        $customer = Customer::findOrFail($id);
        $customer->delete();
        session()->flash('success', 'Customer deleted successfully.');
        return to_route('backend.admin.customers.index');
    }
    public function getCustomers(Request $request)
    {
        $businessId = session('business_id');


        if ($request->wantsJson()) {
            return response()->json(Customer::where('business_id', $businessId)->latest()->get());
        }
    }
    //get orders by customer id
    public function orders($id)
    {
        $customer = Customer::findOrFail($id);
        $orders = $customer->orders()->paginate(100);
        return view('backend.orders.index', compact('orders'));
    }

    public function selectCustomer($id)
    {
        // Example: store selected customer in session
        $customerData = Customer::findOrFail($id);
        $isRegistered = $customerData->is_registered;

        return response()->json([
            'success' => true,
            'message' => 'Customer selected successfully',
            'customer' => $customerData,
            'isRegistered' => $isRegistered,
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->get('term');
        $businessId = session('business_id');

        $customers = Customer::where('business_id', $businessId)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', '%' . $query . '%')
                    ->orWhere('phone', 'LIKE', '%' . $query . '%')
                    ->orWhere('address', 'LIKE', '%' . $query . '%');
            })
            ->latest()
            ->limit(20)
            ->get(['id', 'name', 'phone', 'address']);

        return response()->json($customers);
    }

    public function isRegisteredCustomer($cnic)
    {

        try {
             
          $businessType = getBusinessType();

            if ($businessType === 'both') {

                $settings = FbrTokenService::getSettings();

                $env = $settings->environment == 1 ? 'production' : 'sandbox';

                $digitalToken = $settings->{"digital_{$env}_token"};
                $posToken     = $settings->{"pos_{$env}_token"};

                // pehle digital, agar empty ho to pos
                $token = !empty($digitalToken) ? $digitalToken : $posToken;

            } else {
                $token = FbrTokenService::getToken();
            }
           
            $response = Http::withHeaders([
                 'Authorization' => "Bearer $token",
                    'Content-Type' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Accept' => '*/*'
            ])->get('https://gw.fbr.gov.pk/dist/v1/Get_Reg_Type', [
                        'Registration_No' => $cnic
                    ]);

            $data = $response->json();
            // check response
            if (isset($data['REGISTRATION_TYPE'])) {

                return response()->json([
                    'registered' => $data['REGISTRATION_TYPE'] === 'Registered'
                ]);
            }

            return response()->json(['registered' => false]);

        } catch (\Exception $e) {
            return response()->json(['registered' => false]);
        }
    }
}