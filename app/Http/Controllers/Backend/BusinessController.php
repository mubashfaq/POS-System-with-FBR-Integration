<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Business;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Auth;
use Rinvex\Country\CountryLoader;

class BusinessController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // abort_if(!auth()->user()->can('business_view'), 403);

    if ($request->ajax()) {
        $businesses = Business::latest()->get();

        return DataTables::of($businesses)
            ->addIndexColumn()
            ->addColumn('business_name', fn($data) => $data->business_name)
            ->addColumn('industry', fn($data) => $data->industry ?? '-')
            ->addColumn('ntn', fn($data) => $data->ntn ?? '-')
            ->addColumn('country', fn($data) => $data->country ?? '-')
            ->addColumn('city', fn($data) => $data->city ?? '-')
            ->addColumn('action', function ($data) {
                return '<div class="btn-group">
                    <button type="button" class="btn btn-flat">Action</button>
                    <button type="button" class="btn btn-flat dropdown-toggle dropdown-icon" data-toggle="dropdown" aria-expanded="false">
                      <span class="sr-only">Toggle Dropdown</span>
                    </button>
                    <div class="dropdown-menu" role="menu">
                      <a class="dropdown-item" href="' . route('backend.admin.business.edit', $data->id) . '" >
                        <i class="fas fa-edit"></i> Edit
                      </a>
                    </div>
                  </div>';
            })
            ->rawColumns(['status','action'])
            ->toJson();
    }

    return view('backend.business.index');

    }

    /**
     * Show the form for creating a new resource.
     */
    // public function create()
    // {
    //     // yahan se sab countries laa rahe hain
    //     $countries = countries('en');

    //     return view('backend.business.create', compact('countries'));
    // }

    public function create(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'industry' => 'required|string|max:255',
            'ntn' => 'nullable|string|max:255',
            'country' => 'required|string|max:255',
            'city' => 'required|string|max:255',
        ]);

        $business = Business::create($request->all());
        
        // Attach business to user
        Auth::user()->businesses()->attach($business->id);

        // Store selected business in session
        session(['current_business_id' => $business->id]);

        return redirect()->route('backend.admin.dashboard')->with('success', 'Business created successfully');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'industry'      => 'nullable|string|max:255',
            'ntn'           => 'nullable|string|max:255',
            'country'       => 'nullable|string|max:255',
            'city'          => 'nullable|string|max:255',
        ]);

        Business::create($request->only(['business_name','industry','ntn','country','city']));

        return redirect()->route('backend.admin.business.index')
                         ->with('success', 'Business created successfully!');
    }
        


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
        // abort_if(!auth()->user()->can('business_update'), 403);

        $business = Business::findOrFail($id);
        return view('backend.business.edit', compact('business'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
    // abort_if(!auth()->user()->can('business_update'), 403);

    $validated = $request->validate([
        'business_name' => 'required|string|max:255',
        'industry'      => 'nullable|string|max:255',
        'ntn'           => 'nullable|string|max:50',
        'country'       => 'nullable|string|max:100',
        'city'          => 'nullable|string|max:100',
    ]);

    $business = Business::findOrFail($id);
    $business->update($validated);

    return redirect()->route('backend.admin.business.index')
        ->with('success', 'Business updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // abort_if(!auth()->user()->can('business_delete'), 403);

        $business = Business::findOrFail($id);
        $business->delete();

        return redirect()->back()->with('success', 'Business deleted successfully!');
    }




    // Login time buiness create or select logic

    public function selection()
    {
        $user = Auth::user();
        $businesses = $user->businesses;
        
        return view('frontend.authentication.business-selection', compact('businesses'));
    }

    public function select(Request $request)
    {
        $businessId = $request->business_id;
        $request->validate([
            'business_id' => 'required|exists:businesses,id'
        ]);

        // Check if user has access to this business
        $user = Auth::user();
        if (!$user->businesses()->where('business_id', $request->business_id)->exists()) {
            return back()->with('error', 'You do not have access to this business');
        }

        // Store selected business in session
        session(['business_id' => $businessId]);

        return redirect()->route('backend.admin.dashboard')->with('success', 'Business selected successfully');
    }


}
