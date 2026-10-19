<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Godown;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Validation\ValidationException;

class ImportGodownController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {

            $businessId = session('business_id');

            if (!$businessId) {
                return response()->json(['error' => 'No business selected'], 422);
            }

            $query = Godown::where('business_id', $businessId);

            return DataTables::of($query)
                ->addIndexColumn()

                ->editColumn('location', function ($godown) {
                    return $godown->location ?: '—';
                })

                ->addColumn('status', function ($godown) {
                    return $godown->status
                        ? '<span class="badge badge-success">Active</span>'
                        : '<span class="badge badge-secondary">Inactive</span>';
                })

                ->addColumn('action', function ($godown) {

                    $deleteUrl = route('backend.admin.import-godown.destroy', $godown->id);

                    return '
                    <div class="d-flex gap-1 flex-nowrap">

                        <button type="button" class="btn-expand btn-outline-primary edit-godown"
                                data-id="' . $godown->id . '"
                                data-name="' . e($godown->name) . '"
                                data-location="' . e($godown->location) . '"
                                data-status="' . ($godown->status ? 1 : 0) . '">
                            <i class="fas fa-edit"></i> <span>Edit</span>
                        </button>

                        <form action="' . $deleteUrl . '" method="POST" style="display:inline;"
                            onsubmit="return confirm(\'Are you sure you want to delete this godown?\')">

                            ' . csrf_field() . '

                            <input type="hidden" name="_method" value="DELETE">

                            <button type="submit" class="btn-expand btn-outline-danger">
                                <i class="fas fa-trash"></i> <span>Delete</span>
                            </button>

                        </form>

                    </div>
                ';
                })

                ->rawColumns(['status', 'action'])

                ->toJson();
        }

        return view('backend.imports.godown.index');
    }

    public function store(Request $request)
    {
        $businessId = session('business_id');

        if (!$businessId) {
            throw ValidationException::withMessages(['No business selected in session.',]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255',],
            'status' => ['nullable', 'boolean',],
        ]);

        $slug = Str::slug($validated['name']);

        $exists = Godown::where('business_id', $businessId)->where('slug', $slug)->exists();

        if ($exists) {
            return response()->json(['message' => 'This Godown already exists.'], 422);
        }

        Godown::create([
            'business_id' => $businessId,
            'name' => $validated['name'],
            'slug' => $slug,
            'location' => $request->location,
            'status' => $request->boolean('status'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Godown created successfully.'
        ]);
    }

    public function update(Request $request, Godown $godown)
    {
        if ($godown->business_id != session('business_id')) {
            throw ValidationException::withMessages([
                'business_id' => ['The session Business ID does not match the Edit Business ID.'],
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255',],
            'status' => ['nullable', 'boolean',],
        ]);

        $slug = Str::slug($validated['name']);

        $exists = Godown::where('business_id', session('business_id'))->where('slug', $slug)
            ->where('id', '!=', $godown->id)->exists();

        if ($exists) {
            return response()->json(['message' => 'This Godown already exists.'], 422);
        }

        $godown->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'status' => $request->boolean('status'),
            'location' => $request->location,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Godown updated successfully.'
        ]);
    }

    public function destroy(Godown $godown)
    {
        if ($godown->business_id != session('business_id')) {
            throw ValidationException::withMessages([
                'business_id' => ['The session Business ID does not match the Delete Business ID.'],
            ]);
        }

        // if ($godown->importExpenses()->exists()) {
        //     return redirect()->back()->with('warning', 'This godown is already used in imports and cannot be deleted.');
        // }

        $godown->delete();

        return redirect()->back()->with('success', 'Godown deleted successfully.');
    }
}