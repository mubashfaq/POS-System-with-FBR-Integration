<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class PlanController extends Controller
{
    public function __construct(private PlanService $planService) {}

    public function choosePlan()
    {
        $plans       = Plan::active()->get();
        $user        = auth()->user();
        $currentPlan = null;
        $usage       = null;
        $businessType = null;

        if ($user) {
            try {
                $sub         = $this->planService->getSubscription();
                $currentPlan = $sub->plan;
                $usage       = $this->planService->usageFor();
                $businessType = $this->planService->businessType();
            } catch (\Exception $e) {
                // No subscription yet — show plans without highlighting
            }
        }
        return view('backend.plans.choose-plan', compact('plans', 'currentPlan', 'usage', 'businessType'));
    }

    public function indexFront()
    {
        $plans       = Plan::active()->get();
        $user        = auth()->user();
        $currentPlan = null;
        $usage       = null;
        $businessType = null;

        if ($user) {
            try {
                $sub         = $this->planService->getSubscription();
                $currentPlan = $sub->plan;
                $usage       = $this->planService->usageFor();
                $businessType = $this->planService->businessType();
            } catch (\Exception $e) {
                // No subscription yet — show plans without highlighting
            }
        }

        return view('frontend.plans.index', compact('plans', 'currentPlan', 'usage', 'businessType'));
    }

    // All available features across all plans
    // Add more here as your POS grows
    public const FEATURES = [
        'reports' => 'Reports',
        // 'exports' => 'Exports',
        // 'barcode' => 'Barcode Scanner',
    ];

    public function index(Request $request)
    {
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403);
        };
        if ($request->ajax()) {

            $plans = Plan::withCount([
                'subscriptions as active_subscribers' => function ($q) {
                    $q->where('status', 'active');
                }
            ]);

            if ($request->has('order')) {

                // DataTables column index
                $columnIndex = $request->input('order.0.column');
                $direction   = $request->input('order.0.dir');

                // Map DataTable columns to DB columns
                $columns = [
                    1 => 'name',
                    2 => 'price',
                    3 => 'max_pos_invoices',
                    4 => 'max_digital_invoices',
                    5 => 'max_users',
                    7 => 'is_active',
                ];

                if (isset($columns[$columnIndex])) {
                    $plans->orderBy($columns[$columnIndex], $direction);
                }

                // ✅ Always apply sort_order as secondary
                $plans->orderBy('sort_order', 'asc');
            } else {
                // ✅ Initial load (default)
                $plans->orderBy('sort_order', 'asc');
            }

            return DataTables::of($plans)
                ->addIndexColumn()

                ->addColumn('plan', function ($data) {
                    return '
                        <div>' . $data->name . '</div>
                    ';
                })

                ->addColumn('price', function ($data) {
                    if ($data->price == 0) {
                        return '<span class="badge bg-success bg-opacity-10 text-success">Free</span>';
                    }
                    return '<span>' . number_format($data->price, 2) . '</span>';
                })

                ->addColumn(
                    'pos_invoices',
                    fn($data) =>
                    $data->limitLabel('max_pos_invoices')
                )

                ->addColumn(
                    'digital_invoices',
                    fn($data) =>
                    $data->limitLabel('max_digital_invoices')
                )

                ->addColumn('total_invoices', function ($data) {

                    $combined = $data->combinedInvoiceLimit();
                
                    $value = $combined === -1 ? 'Unlimited' : $combined;
                
                    return '<span class="fw-semibold">'.$value.'</span>';
                })

                ->addColumn(
                    'users',
                    fn($data) =>
                    $data->limitLabel('max_users')
                )

                // ->addColumn('products', fn($data) =>
                //     $data->limitLabel('max_products')
                // )

                // ->addColumn('features', function ($data) {
                //     if (empty($data->features)) {
                //         return '<span>None</span>';
                //     }

                //     $badges = '';
                //     foreach ($data->features as $feature) {
                //         $badges .= '<span class="badge bg-primary text-primary small ml-1">'
                //             . $feature . '</span>';
                //     }

                //     return $badges;
                // })

                ->addColumn('subscribers', function ($data) {
                    $count = $data->active_subscribers;

                    return '<span class="badge ' .
                        ($count > 0 ? 'bg-info bg-opacity-10 text-info' : 'bg-light text-muted') .
                        '">' . $count . ' business' . ($count > 1 ? 'es' : '') . '</span>';
                })

                ->addColumn('status', function ($data) {
                    return $data->is_active
                        ? '<span class="badge bg-primary">Active</span>'
                        : '<span class="badge bg-danger">Inactive</span>';
                })

                ->addColumn('action', function ($data) {
                    return '
                    <div class="btn-group">
                        <button type="button" class="btn btn-flat" data-toggle="dropdown">Action</button>
                        <button type="button" class="btn btn-flat dropdown-toggle dropdown-icon" data-toggle="dropdown"></button>

                        <div class="dropdown-menu">

                            <a class="dropdown-item" href="' . route('backend.admin.plans.edit', $data->id) . '">
                                <i class="fas fa-edit"></i> Edit
                            </a>

                            <form action="' . route('backend.admin.plans.toggle', $data->id) . '" method="POST">
                                ' . csrf_field() . '
                                <button type="submit" class="dropdown-item">
                                <i class="fas ' . ($data->is_active ? 'fa-toggle-off text-danger' : 'fa-toggle-on text-success') . '"></i>
                                    ' . ($data->is_active ? 'Deactivate' : 'Activate') . '
                                </button>
                            </form>

                            <div class="dropdown-divider"></div>

                            <form action="' . route('backend.admin.plans.destroy', $data->id) . '" method="POST">
                                ' . csrf_field() . method_field("DELETE") . '
                                <button type="submit" class="dropdown-item text-danger"
                                    onclick="return confirm(\'Delete this plan?\')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>

                        </div>
                    </div>';
                })

                ->rawColumns([
                    'plan',
                    'price',
                    'total_invoices',
                    // 'features',
                    'subscribers',
                    'status',
                    'action'
                ])
                ->make(true);
        }

        return view('backend.plans.index');
    }


    public function create()
    {
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403);
        };
        // $features   = self::FEATURES;
        $sortOrder  = Plan::max('sort_order') + 1; // next sort order

        return view('backend.plans.create', compact('sortOrder'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403);
        };
        $data = $this->validatePlan($request);

        Plan::create($data);

        return redirect()->route('backend.admin.plans.index')
            ->with('success', "Plan \"{$data['name']}\" created successfully.");
    }

    public function edit(Plan $plan)
    {
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403);
        };
        // $features = self::FEATURES;

        return view('backend.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403);
        };
        $data = $this->validatePlan($request, $plan->id);

        $plan->update($data);

        return redirect()->route('backend.admin.plans.index')
            ->with('success', "Plan \"{$plan->name}\" updated successfully.");
    }

    public function destroy(Plan $plan)
    {
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403);
        };
        // Prevent deleting a plan that businesses are actively subscribed to
        $activeCount = $plan->subscriptions()->where('status', 'active')->count();

        if ($activeCount > 0) {
            return back()->with(
                'error',
                "Cannot delete \"{$plan->name}\" — {$activeCount} business(es) are currently on this plan. Deactivate it instead."
            );
        }

        $name = $plan->name;
        $plan->delete();

        return redirect()->route('backend.admin.plans.index')
            ->with('success', "Plan \"{$name}\" deleted.");
    }

    public function toggle(Plan $plan)
    {
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403);
        };
        $plan->update(['is_active' => !$plan->is_active]);

        $status = $plan->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Plan \"{$plan->name}\" {$status}.");
    }

    public function reorder(Request $request)
    {
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403);
        };
        $request->validate([
            'order'   => 'required|array',
            'order.*' => 'exists:plans,id',
        ]);

        foreach ($request->order as $sortOrder => $planId) {
            Plan::where('id', $planId)->update(['sort_order' => $sortOrder + 1]);
        }

        return response()->json(['success' => true]);
    }

    private function validatePlan(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100',
            'slug'         => 'required|string|max:100|alpha_dash|unique:plans,slug' . ($ignoreId ? ",{$ignoreId}" : ''),
            'price'        => 'required|numeric|min:0',
            'max_pos_invoices'     => 'required|integer|min:-1',
            'max_digital_invoices' => 'required|integer|min:-1',
            'max_users'    => 'required|integer|min:-1',
            // 'max_products' => 'required|integer|min:-1',
            'features'     => 'nullable|array',
            'features.*'   => 'string|in:' . implode(',', array_keys(self::FEATURES)),
            'is_active'    => 'boolean',
            'sort_order'   => 'required|integer|min:1',
        ]);

        // Ensure features is always an array (empty if none selected)
        $validated['features'] = $validated['features'] ?? [];

        // Normalize checkbox to boolean
        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }
}
