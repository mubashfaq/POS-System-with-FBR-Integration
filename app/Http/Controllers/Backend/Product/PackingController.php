<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\ImportProduct;
use App\Models\OrderProduct;
use App\Models\Packing;
use App\Models\PackingLevel;
use App\Models\PosCart;
use App\Models\Product;
use App\Models\ProductUnitPrice;
use App\Models\ProductVariation;
use App\Models\Unit;
use App\Services\PackingCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\DataTables;

class PackingController extends Controller
{
    public function index(Request $request)
    {
        $businessId = session('business_id');

        if ($request->ajax()) {
            $packings = Packing::with(['baseUnit', 'levels.unit'])
                ->where(function ($q) use ($businessId) {
                    $q->where('business_id', $businessId);
                    if (!$businessId) {
                        $q->orWhereNull('business_id');
                    }
                })
                ->latest()
                ->get();

            return DataTables::of($packings)
                ->addIndexColumn()
                ->addColumn('name', fn($data) => $data->name)
                ->addColumn('base_unit', fn($data) => $data->baseUnit?->title ?? '-')
                ->addColumn('summary', fn($data) => $data->summary)
                ->addColumn('status', fn($data) => $data->is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>')
                ->addColumn('action', function ($data) {
                    return '<div class="btn-group">
                    <button type="button" class="btn btn-flat">Action</button>
                    <button type="button" class="btn btn-flat dropdown-toggle dropdown-icon" data-toggle="dropdown" aria-expanded="false">
                      <span class="sr-only">Toggle Dropdown</span>
                    </button>
                    <div class="dropdown-menu" role="menu">
                      <a class="dropdown-item" href="' . route('backend.admin.packings.edit', $data->id) . '">
                        <i class="fas fa-edit"></i> Edit
                      </a>
                      <div class="dropdown-divider"></div>
                      <form action="' . route('backend.admin.packings.destroy', $data->id) . '" method="POST" style="display:inline;">
                        ' . csrf_field() . '
                        ' . method_field("DELETE") . '
                        <button type="submit" class="dropdown-item" onclick="return confirm(\'Are you sure you want to delete/deactivate this packing?\')">
                          <i class="fas fa-trash"></i> Delete
                        </button>
                      </form>
                    </div>';
                })
                ->rawColumns(['status', 'action'])
                ->toJson();
        }

        return view('backend.packings.index');
    }

    public function create()
    {
        $units = Unit::where('is_active', true)->orderBy('title')->get();
        return view('backend.packings.form', compact('units'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'is_active' => 'nullable|boolean',
            'levels' => 'required|array|min:1|max:3',
            'levels.*.unit_id' => 'required|exists:units,id|distinct',
            'levels.*.contains_qty' => 'nullable|numeric|gt:0',
        ]);

        $validator->after(function ($v) use ($request) {
            $levels = $request->input('levels', []);
            $count = count($levels);
            if ($count > 0) {
                // Every level except the last needs contains_qty
                for ($i = 0; $i < $count - 1; $i++) {
                    if (empty($levels[$i]['contains_qty']) || (float) $levels[$i]['contains_qty'] <= 0) {
                        $v->errors()->add("levels.{$i}.contains_qty", "Contains quantity is required for level " . ($i + 1));
                    }
                }
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $businessId = session('business_id');
        $rawLevels = array_values($request->input('levels', []));

        // Number levels 1..n
        foreach ($rawLevels as $idx => &$item) {
            $item['level'] = $idx + 1;
        }
        unset($item);

        $calculatedLevels = PackingCalculator::withBaseQty($rawLevels);
        $lastLevel = end($calculatedLevels);
        $baseUnitId = $lastLevel['unit_id'];

        DB::transaction(function () use ($request, $businessId, $baseUnitId, $calculatedLevels) {
            $packing = Packing::create([
                'business_id' => $businessId,
                'name' => $request->name,
                'base_unit_id' => $baseUnitId,
                'levels_count' => count($calculatedLevels),
                'is_active' => $request->has('is_active') ? (bool) $request->is_active : true,
            ]);

            foreach ($calculatedLevels as $lvl) {
                PackingLevel::create([
                    'packing_id' => $packing->id,
                    'level' => $lvl['level'],
                    'unit_id' => $lvl['unit_id'],
                    'contains_qty' => $lvl['contains_qty'],
                    'base_qty' => $lvl['base_qty'],
                ]);
            }
        });

        return redirect()->route('backend.admin.packings.index')->with('success', 'Packing created successfully!');
    }

    public function edit($id)
    {
        $packing = Packing::with('levels.unit')->findOrFail($id);
        $units = Unit::where('is_active', true)->orderBy('title')->get();

        $isUsed = ImportProduct::where('packing_id', $packing->id)->exists()
            || OrderProduct::where('packing_id', $packing->id)->exists()
            || PosCart::where('packing_id', $packing->id)->exists()
            || Product::where('default_packing_id', $packing->id)->exists();

        return view('backend.packings.form', compact('packing', 'units', 'isUsed'));
    }

    public function update(Request $request, $id)
    {
        $packing = Packing::findOrFail($id);

        $isUsed = ImportProduct::where('packing_id', $packing->id)->exists()
            || OrderProduct::where('packing_id', $packing->id)->exists()
            || PosCart::where('packing_id', $packing->id)->exists()
            || Product::where('default_packing_id', $packing->id)->exists();

        if ($isUsed) {
            // Level edits blocked when in use
            $request->validate([
                'name' => 'required|string|max:150',
                'is_active' => 'nullable|boolean',
            ]);

            $packing->update([
                'name' => $request->name,
                'is_active' => $request->has('is_active') ? (bool) $request->is_active : false,
            ]);

            return redirect()->route('backend.admin.packings.index')
                ->with('info', 'Packing details updated. Level modifications were locked because this packing is already in use.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'is_active' => 'nullable|boolean',
            'levels' => 'required|array|min:1|max:3',
            'levels.*.unit_id' => 'required|exists:units,id|distinct',
            'levels.*.contains_qty' => 'nullable|numeric|gt:0',
        ]);

        $validator->after(function ($v) use ($request) {
            $levels = $request->input('levels', []);
            $count = count($levels);
            if ($count > 0) {
                for ($i = 0; $i < $count - 1; $i++) {
                    if (empty($levels[$i]['contains_qty']) || (float) $levels[$i]['contains_qty'] <= 0) {
                        $v->errors()->add("levels.{$i}.contains_qty", "Contains quantity is required for level " . ($i + 1));
                    }
                }
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $rawLevels = array_values($request->input('levels', []));
        foreach ($rawLevels as $idx => &$item) {
            $item['level'] = $idx + 1;
        }
        unset($item);

        $calculatedLevels = PackingCalculator::withBaseQty($rawLevels);
        $lastLevel = end($calculatedLevels);
        $baseUnitId = $lastLevel['unit_id'];

        DB::transaction(function () use ($packing, $request, $baseUnitId, $calculatedLevels) {
            $packing->update([
                'name' => $request->name,
                'base_unit_id' => $baseUnitId,
                'levels_count' => count($calculatedLevels),
                'is_active' => $request->has('is_active') ? (bool) $request->is_active : false,
            ]);

            $packing->levels()->delete();

            foreach ($calculatedLevels as $lvl) {
                PackingLevel::create([
                    'packing_id' => $packing->id,
                    'level' => $lvl['level'],
                    'unit_id' => $lvl['unit_id'],
                    'contains_qty' => $lvl['contains_qty'],
                    'base_qty' => $lvl['base_qty'],
                ]);
            }
        });

        return redirect()->route('backend.admin.packings.index')->with('success', 'Packing updated successfully!');
    }

    public function destroy($id)
    {
        $packing = Packing::findOrFail($id);

        $isUsed = ImportProduct::where('packing_id', $packing->id)->exists()
            || OrderProduct::where('packing_id', $packing->id)->exists()
            || PosCart::where('packing_id', $packing->id)->exists()
            || Product::where('default_packing_id', $packing->id)->exists();

        if ($isUsed) {
            $packing->update(['is_active' => false]);
            return redirect()->back()->with('warning', 'Packing is in use and cannot be removed. It has been deactivated instead.');
        }

        $packing->delete();
        return redirect()->back()->with('success', 'Packing deleted successfully!');
    }

    /**
     * JSON API returning levels of a packing.
     * Optional ?product_id=&variation_id= includes suggested_price.
     */
    public function levels(Request $request, $id)
    {
        $packing = Packing::with('levels.unit')->findOrFail($id);

        $productId = $request->query('product_id');
        $variationId = $request->query('variation_id');

        $product = $productId ? Product::find($productId) : null;
        $variation = $variationId ? ProductVariation::find($variationId) : null;

        $baseUnitPrice = null;
        if ($product) {
            $baseUnitPrice = $variation && $variation->price > 0 ? (float) $variation->price : (float) $product->discounted_price;
        }

        $result = $packing->levels->map(function ($lvl) use ($product, $baseUnitPrice) {
            $data = [
                'unit_id' => $lvl->unit_id,
                'name' => $lvl->unit?->title ?? '',
                'short_name' => $lvl->unit?->short_name ?? '',
                'level' => $lvl->level,
                'contains_qty' => (float) $lvl->contains_qty,
                'base_qty' => (float) $lvl->base_qty,
            ];

            if ($product !== null) {
                // Check product_unit_prices first
                $unitPriceRow = ProductUnitPrice::where('product_id', $product->id)->where('unit_id', $lvl->unit_id)->first();

                if ($unitPriceRow) {
                    $data['suggested_price'] = (float) $unitPriceRow->sale_price;
                } else {
                    $data['suggested_price'] = round((float) $baseUnitPrice * (float) $lvl->base_qty, 4);
                }
            }

            return $data;
        });

        return response()->json([
            'id' => $packing->id,
            'name' => $packing->name,
            'base_unit_id' => $packing->base_unit_id,
            'summary' => $packing->summary,
            'levels' => $result,
        ]);
    }
}

