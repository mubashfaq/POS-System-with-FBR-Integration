<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('supplier_view'), 403);

        
    // abort_if(!auth()->user()->can('supplier_view'), 403);

        $businessId = session('business_id');
        
        if ($request->ajax()) {
            $suppliers = Supplier::where('business_id', $businessId) // 👈 filter by business
            ->latest()
            ->get();
            return DataTables::of($suppliers)
                ->addIndexColumn()
                ->addColumn('name', fn($data) => $data->name)
                ->addColumn('phone', fn($data) => $data->phone)
                ->addColumn('address', fn($data) => $data->address)
                ->addColumn('created_at', fn($data) => $data->created_at->format('d M, Y')) // Using Carbon for formatting
                ->addColumn('action', function ($data) {
                    return '<div class="btn-group">
                    <button type="button" class="btn  btn-flat">Action</button>
                    <button type="button" class="btn  btn-flat dropdown-toggle dropdown-icon" data-toggle="dropdown" aria-expanded="false">
                      <span class="sr-only">Toggle Dropdown</span>
                    </button>
                    <div class="dropdown-menu" role="menu">
                      <a class="dropdown-item" href="' . route('backend.admin.suppliers.edit', $data->id) . '" ' . ($data->id == 1 ? 'onclick="event.preventDefault();"' : '') . ' >
                    <i class="fas fa-edit"></i> Edit
                </a> <div class="dropdown-divider"></div>
<form action="' . route('backend.admin.suppliers.destroy', $data->id) . '"method="POST" style="display:inline;">
                   ' . csrf_field() . '
                    ' . method_field("DELETE") . '
<button type="submit" ' . ($data->id == 1 ? 'disabled' : '') . ' class="dropdown-item" onclick="return confirm(\'Are you sure ?\')"><i class="fas fa-trash"></i> Delete</button>
                  </form>
                    </div>
                  </div>';
                })
                ->rawColumns(['name', 'phone', 'address', 'created_at', 'action'])
                ->toJson();
        }
        if ($request->wantsJson()) {

            return response()->json(Supplier::where('business_id', $businessId)->latest()->get());
        }


        return view('backend.suppliers.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('supplier_create'), 403);

    // abort_if(!auth()->user()->can('supplier_create'), 403);
        return view('backend.suppliers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('supplier_create'), 403);


    // abort_if(!auth()->user()->can('supplier_create'), 403);
        if ($request->wantsJson()) {
            $request->validate([
                'name' => 'required|string',
            ]);

            $supplier = Supplier::create([
                'name' => $request->name,
            ]);

            return response()->json($supplier);
        }
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:suppliers,phone',
            'address' => 'nullable|string|max:255',
        ]);

        $businessId = session('business_id'); // business_id 
        if (!$businessId) {
            return redirect()->back()->with('error', 'No business selected.');
        }

        $supplier = Supplier::create([
            'name'        => $request->name,
            'phone'       => $request->phone,
            'address'     => $request->address,
            'business_id' => $businessId, 
        ]);

        session()->flash('success', 'Supplier created successfully.');
        return to_route('backend.admin.suppliers.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('supplier_update'), 403);

        // abort_if(!auth()->user()->can('supplier_update'), 403);
        $supplier = Supplier::findOrFail($id);
        return view('backend.suppliers.edit', compact('supplier'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('supplier_update'), 403);

        // abort_if(!auth()->user()->can('supplier_update'), 403);
        $supplier = Supplier::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:suppliers,phone,' . $supplier->id, // Corrected syntax
            'address' => 'nullable|string|max:255',
        ]);

        $supplier->update($request->only(['name', 'phone', 'address']));

        session()->flash('success', 'Supplier updated successfully.');
        return to_route('backend.admin.suppliers.index');
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('supplier_delete'), 403);

        // abort_if(!auth()->user()->can('supplier_delete'), 403);
        $supplier = Supplier::findOrFail($id);
        $supplier->delete();
        session()->flash('success', 'Supplier deleted successfully.');
        return to_route('backend.admin.suppliers.index');
    }
    public function getCustomers(Request $request)
    {
        if ($request->wantsJson()) {
            return response()->json(Supplier::latest()->get());
        }
    }
    //get orders by supplier id
    public function orders($id)
    {
        $supplier = Supplier::findOrFail($id);
        $orders = $supplier->orders()->paginate(100);
        return view('backend.orders.index', compact('orders'));
    }
}