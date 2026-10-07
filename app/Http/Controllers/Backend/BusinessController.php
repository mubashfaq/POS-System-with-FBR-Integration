<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Business;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
     * Store a newly created resource in storage.
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'industry' => 'required|string|max:255',
            'ntn' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{7}$|^[A-Za-z0-9-]{9}$|^[A-Za-z0-9-]{13}$/'],
            'business_type' => 'required|in:pos,digital,pral,both',
            'country' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'nullable|string|max:50',
            'cnic' => ['required', 'string', 'string', 'regex:/^[A-Za-z0-9-]{7}$|^[A-Za-z0-9-]{9}$|^[A-Za-z0-9-]{13}$/'],
            'state'           => 'required|string|max:50',
            'logo'            => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'currency'        => 'nullable|string|max:10',
            'facebook_link'   => 'nullable|url|max:255',
            'whatsapp_link'   => 'nullable|url|max:255',
            'youtube_link'    => 'nullable|url|max:255',
            'instagram_link'  => 'nullable|url|max:255',
            'enable_batch'    => 'nullable|boolean',
            'address'         => 'required|string',
        ],  [
            'ntn.regex' => 'NTN must contain exactly 7, 9 or 13 characters.',
            'cnic.regex' => 'CNIC must contain exactly 7, 9 or 13 characters.',
        ]);
        // File Upload
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('logos', 'public');
            $validated['logo'] = $logoPath; // yaha sahi path add kar rahe ho
        } else {
            // Assign default logo
            $validated['logo'] = 'logos/default-business.png';
        }

        // Ab $validated use karo instead of $request->all()
        $validated['enable_batch'] = $request->boolean('enable_batch');
        DB::beginTransaction();

        try {

            $business = Business::create($validated);

            DB::table('quantity_validations')->insert([
                'business_id' => $business->id,
            ]);

            DB::table('fbr_tokens')->insert([
                'business_id' => $business->id,
            ]);

            // Attach business to user
            Auth::user()->businesses()->attach($business->id);

                // ✅ Create UserBusiness record
            $userBusiness = \App\Models\UserBusiness::where('user_id', Auth::id())
                ->where('business_id', $business->id)
                ->first();

            if ($userBusiness) {
                $userBusiness->assignRole(1);
            }

            // Store selected business in session
            session(['business_id' => $business->id]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->route('backend.admin.dashboard')->with('success', 'Business created successfully');
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
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('business_update'), 403);

        $currentBusinessId = session('business_id');
        
        // Agar requested ID current session business se different hai
        if ($id != $currentBusinessId) {
            return redirect()->route('backend.admin.business.edit', $currentBusinessId);
        }

        $business = Business::findOrFail($id);
        return view('backend.business.edit', compact('business'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {

        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'industry'      => 'nullable|string|max:255',
            'ntn' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{7}$|^[A-Za-z0-9-]{9}$|^[A-Za-z0-9-]{13}$/'], 
            'country'       => 'nullable|string|max:100',
            'city'          => 'nullable|string|max:100',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'nullable|string|max:50',
            'logo'            => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'currency'        => 'nullable|string|max:10',
            'facebook_link'   => 'nullable|url|max:255',
            'whatsapp_link'   => 'nullable|url|max:255',
            'youtube_link'    => 'nullable|url|max:255',
            'instagram_link'  => 'nullable|url|max:255',
            'address' => 'nullable',
            'enable_batch'    => 'nullable|boolean',
        ],  [
            'ntn.regex' => 'NTN must contain exactly 7, 9 or 13 characters.',
        ]);

        $business = Business::findOrFail($id);
        
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($business->logo && Storage::disk('public')->exists($business->logo)) {
                Storage::disk('public')->delete($business->logo);
            }
            
            $logoPath = $request->file('logo')->store('logos', 'public');
            $validated['logo'] = $logoPath;
        } else {
            // Keep the existing logo if no new file is uploaded
            $validated['logo'] = $business->logo;
        }

        $validated['enable_batch'] = $request->boolean('enable_batch');
        $business->update($validated);


            // ✅ Update session if this business is currently selected
        if (session('business_id') == $business->id) {
            session(['business_id' => $business->id]);
            session(['business_name' => $business->business_name]);
        }

        return redirect()->route('backend.admin.business.edit', $id)->with('success', 'Business updated successfully!');
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
    if (!Auth::check()) {
        return view('frontend.authentication.business-selection');
    } 

    $user = Auth::user();
    $businesses = $user->hasRole('super_admin') ? Business::all() : $user->businesses;

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
        if (!$user->hasRole('super_admin') && !$user->businesses()->where('business_id', $request->business_id)->exists()) {
            return back()->with('error', 'You do not have access to this business');
        }

        // Store selected business in session
        session(['business_id' => $businessId]);

        return redirect()->route('backend.admin.dashboard')->with('success', 'Business selected successfully');
    }


    public function switchBusiness(Request $request)
    {
        $businessId = $request->input('business_id');

        // dd($businessId);
        // Check that this business belongs to the current user
        if (!auth()->user()->hasRole('super_admin') && !auth()->user()->businesses()->where('businesses.id', $businessId)->exists()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Store in session
        $business = \App\Models\Business::find($businessId);
        session(['business_id' => $business->id]);
        session(['business_name' => $business->business_name]);

        Log::info("success");
        return response()->json(['success' => true]);
    }
    

}
