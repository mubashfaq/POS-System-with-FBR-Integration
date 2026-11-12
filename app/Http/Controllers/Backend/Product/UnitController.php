<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\ImportProduct;
use App\Models\Packing;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('unit_view'), 403);

        // abort_if(!auth()->user()->can('unit_view'), 403);
        if ($request->ajax()) {
            $units = Unit::latest()->get();
            return DataTables::of($units)
                ->addIndexColumn()
                ->addColumn('title', fn($data) => $data->title)
                ->addColumn('short_name', fn($data) => $data->short_name)
                ->addColumn('status', fn($data) => $data->is_active ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-secondary">Inactive</span>')
                ->addColumn('action', function ($data) {
                    return '<div class="btn-group">
                        <button type="button" class="btn btn-flat">Action</button>
                        <button type="button" class="btn btn-flat dropdown-toggle dropdown-icon" data-toggle="dropdown" 
                            aria-expanded="false"><span class="sr-only">Toggle Dropdown</span>
                        </button>
                        <div class="dropdown-menu" role="menu">
                            <a class="dropdown-item" href="' . route('backend.admin.units.edit', $data->id) . '">
                                <i class="fas fa-edit"></i> Edit
                            </a> 
                            <div class="dropdown-divider"></div>
                            <form action="' . route('backend.admin.units.destroy', $data->id) . '" method="POST" style="display:inline;">
                                ' . csrf_field() . '
                                ' . method_field("DELETE") . '
                                <button type="submit" class="dropdown-item" onclick="return confirm(\'Are you sure ?\')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>';
                })
                ->rawColumns(['title', 'short_name', 'status', 'action'])
                ->toJson();
        }
        return view('backend.units.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('unit_create'), 403);

        return view('backend.units.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('unit_create'), 403);

        $request->validate([
            'title' => 'required|string|max:100',
            'short_name' => 'required|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $data = $request->only(['title', 'short_name']);
        $data['is_active'] = $request->has('is_active') ? (bool) $request->is_active : true;

        Unit::create($data);

        return redirect()->route('backend.admin.units.index')->with('success', 'Unit created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('unit_update'), 403);

        $unit = Unit::findOrFail($id);
        return view('backend.units.edit', compact('unit'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('unit_update'), 403);

        $request->validate([
            'title' => 'required|string|max:100',
            'short_name' => 'required|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $unitToUpdate = Unit::findOrFail($id);
        $data = $request->only(['title', 'short_name']);
        $data['is_active'] = $request->has('is_active') ? (bool) $request->is_active : false;

        $unitToUpdate->update($data);
        return redirect()->route('backend.admin.units.index')->with('success', 'Unit updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $currentBusiness = currentUserBusiness();
        abort_if(!$currentBusiness || !$currentBusiness->hasPermission('unit_delete'), 403);

        $unit = Unit::findOrFail($id);

        // Never hard-delete a unit used in packing_levels, products.unit_id or import_products.unit_id; set is_active = false
        $isUsed = $unit->packingLevels()->exists() || Product::where('unit_id', $unit->id)->exists()
            || $unit->importProducts()->exists() || ImportProduct::where('qty_unit_id', $unit->id)->exists()
            || Packing::where('base_unit_id', $unit->id)->exists();

        if ($isUsed) {
            $unit->update(['is_active' => false]);
            return redirect()->back()->with('warning', 'Unit is in use and cannot be deleted. It has been deactivated instead.');
        }

        $unit->delete();
        return redirect()->back()->with('success', 'Unit Deleted Successfully');
    }
}
