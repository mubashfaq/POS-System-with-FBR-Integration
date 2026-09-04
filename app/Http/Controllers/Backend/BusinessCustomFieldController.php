<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BusinessCustomField;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BusinessCustomFieldController extends Controller
{

    public function index()
    {
        $businessId = session('business_id');
        return response()->json(
            BusinessCustomField::where('business_id', $businessId)
                ->orderBy('sort_order')->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'field_label' => 'required|string|max:100',
            'field_type' => 'required|in:text,number,select',
            'field_options' => 'nullable|array',
        ]);

        $businessId = session('business_id');

        $field = BusinessCustomField::create([
            'business_id' => $businessId,
            'field_name' => Str::slug($request->field_label, '_'),
            'field_label' => $request->field_label,
            'field_type' => $request->field_type,
            'field_options' => $request->field_options,
            'sort_order' => BusinessCustomField::where('business_id', $businessId)->count(),
            'is_active'    => true,
        ]);

        return response()->json(['message' => 'Field added', 'field' => $field]);
    }

    public function destroy($id)
    {
        $businessId = session('business_id');
        BusinessCustomField::where('business_id', $businessId)
            ->where('id', $id)->delete();
        return response()->json(['message' => 'Field removed']);
    }

    public function toggleActive($id)
    {
        $businessId = session('business_id');
        $field = BusinessCustomField::where('business_id', $businessId)->where('id', $id)->firstOrFail();
        $field->is_active = !$field->is_active;
        $field->save();
        return response()->json(['message' => 'Updated', 'field' => $field]);
    }
}
