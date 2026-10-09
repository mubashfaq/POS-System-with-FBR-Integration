<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Godown;
use App\Models\Import;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ImportController extends Controller
{
    private function getBusinessId(): int
    {
        $businessId = session('business_id');
        abort_if(!$businessId, 403, 'No business selected in session.');
        return (int) $businessId;
    }

    /**
     * Convert empty strings to null so DB accepts them.
     */
    private function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value === '' || $value === null) {
                $data[$key] = null;
            }
        }
        return $data;
    }

    /**
     * Whitelist for the imports table.
     */
    private function allowedImportFields(): array
    {
        return [
            'import_no',
            'container_code',
            'p_date',
            'clear_date',
            'currency',
            'ex_rate',
            'inv_amount',
            'total_expense_1',
            'inv_amount_pkr',
            'godown_id',
            'supplier_id',
            'address',
            'freight_expense',
            'fta_expense',
            'fitting_expense',
            'co_expense',
            'other_expense',
            'total_expense_2',
        ];
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $businessId = session('business_id');

            if (!$businessId) {
                return response()->json(['error' => 'No business selected'], 422);
            }

            $importsQuery = Import::with(['supplier', 'godown', 'expenses'])->where('business_id', $businessId)->orderBy('id', 'desc');

            /* ---------- Date Filter ---------- */
            if ($request->filled('start_date')) {
                $startDate = Carbon::createFromFormat('Y-m-d', $request->input('start_date'))->startOfDay();
                $importsQuery->where('created_at', '>=', $startDate);
            }

            if ($request->filled('end_date')) {
                $endDate = Carbon::createFromFormat('Y-m-d', $request->input('end_date'))->endOfDay();
                $importsQuery->where('created_at', '<=', $endDate);
            }

            $datatable = DataTables::of($importsQuery)
                ->addIndexColumn()

                ->addColumn('import_no', fn($d) => '<strong>' . e($d->import_no) . '</strong>')
                ->addColumn('container_code', fn($d) => $d->container_code ? e($d->container_code) : '-')
                ->addColumn('supplier', fn($d) => $d->supplier?->name ? e($d->supplier->name) : '-')
                ->addColumn('godown', fn($d) => $d->godown?->name ? e($d->godown->name) : '-')
                ->addColumn('p_date', fn($d) => $d->p_date ? Carbon::parse($d->p_date)->format('Y-m-d') : '-')
                ->addColumn('clear_date', fn($d) => $d->clear_date ? Carbon::parse($d->clear_date)->format('Y-m-d') : '-')

                ->addColumn('currency', function ($d) {
                    return $d->currency ? '<span class="badge badge-info">' . e($d->currency) . '</span>' : '-';
                })

                ->addColumn('inv_amount', fn($d) => number_format((float) $d->inv_amount, 2, '.', ','))
                ->addColumn('total_expense_1', fn($d) => number_format((float) $d->total_expense_1, 2, '.', ','))
                ->addColumn('total_expense_2', fn($d) => number_format((float) $d->total_expense_2, 2, '.', ','))

                /** Sum of all CBF/CBM across expense rows */
                ->addColumn('total_cbf_cbm', function ($d) {
                    $sum = $d->expenses->sum('cbf_cbm');
                    return number_format((float) $sum, 2, '.', ',');
                })

                ->addColumn('created_at', fn($d) => $d->created_at ? $d->created_at->format('Y-m-d') : '-')

                ->addColumn('action', function ($d) {
                    $editUrl = route('backend.admin.imports.edit', $d->id);
                    $deleteUrl = route('backend.admin.imports.destroy', $d->id);
                    $csrf = csrf_token();

                    return '
                        <div class="d-flex gap-1 flex-nowrap">
                            <a href="' . $editUrl . '" class="btn-expand btn-outline-primary">
                                <i class="fas fa-edit"></i> <span>Edit</span>
                            </a>

                            <form action="' . $deleteUrl . '" method="POST" style="display:inline;"
                                onsubmit="return confirm(\'Are you sure you want to delete this record?\')">
                                <input type="hidden" name="_token" value="' . $csrf . '">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="btn-expand btn-outline-danger">
                                    <i class="fas fa-trash"></i> <span>Delete</span>
                                </button>
                            </form>
                        </div>
                    ';
                })

                ->rawColumns(['import_no', 'currency', 'action']);

            return $datatable->toJson();
        }

        return view('backend.imports.index');
    }

    public function create()
    {
        $businessId = $this->getBusinessId();

        $suppliers = Supplier::where('business_id', $businessId)->get();
        $categories = Category::where('business_id', $businessId)->where('status', true)->get();
        $godowns = Godown::where('business_id', $businessId)->where('status', true)->get();

        return view('backend.imports.create', compact('categories', 'suppliers', 'godowns'));
    }

    public function store(Request $request)
    {
        $businessId = $this->getBusinessId();

        $request->validate([
            'import_no' => ['required', Rule::unique('imports')->where(fn($q) => $q->where('business_id', $businessId)),],
            'supplier_id' => 'nullable|exists:suppliers,id',
            'godown_id' => 'nullable|exists:godowns,id',
            'expenses' => 'nullable|array',
            'expenses.*.category_id' => 'nullable|integer|exists:categories,id',
            'expenses.*.amount' => 'nullable|numeric|min:0',
            'expenses.*.cbf_cbm' => 'nullable|numeric|min:0',
        ]);

        $data = $this->sanitize($request->only($this->allowedImportFields()));
        $data['business_id'] = $businessId;

        DB::transaction(function () use ($data, $request, $businessId) {
            $import = Import::create($data);
            $this->syncExpenses($import, $request->input('expenses', []), $businessId);
        });

        return redirect()->route('backend.admin.imports.index')->with('success', 'Import record created successfully.');
    }

    public function edit(Import $import)
    {
        $businessId = $this->getBusinessId();

        abort_if($import->business_id != $businessId, 403);

        $suppliers = Supplier::where('business_id', $businessId)->get();
        $categories = Category::where('business_id', $businessId)->where('status', true)->get();
        $godowns = Godown::where('business_id', $businessId)->where('status', true)->get();

        $import->load('expenses.category');

        return view('backend.imports.edit', compact('import', 'categories', 'suppliers', 'godowns'));
    }

    public function update(Request $request, $id)
    {
        $businessId = $this->getBusinessId();
        $import = Import::forCurrentBusiness()->findOrFail($id);

        $request->validate([
            'import_no' => [
                'required',
                Rule::unique('imports')->where(fn($q) => $q->where('business_id', $businessId))->ignore($import->id),
            ],
            'supplier_id' => 'nullable|exists:suppliers,id',
            'godown_id' => 'nullable|exists:godowns,id',
            'expenses' => 'nullable|array',
            'expenses.*.category_id' => 'nullable|integer|exists:categories,id',
            'expenses.*.amount' => 'nullable|numeric|min:0',
            'expenses.*.cbf_cbm' => 'nullable|numeric|min:0',
        ]);

        $data = $this->sanitize($request->only($this->allowedImportFields()));

        DB::transaction(function () use ($import, $data, $request, $businessId) {
            $import->update($data);
            $this->syncExpenses($import, $request->input('expenses', []), $businessId);
        });

        return redirect()->route('backend.admin.imports.index')->with('success', 'Import record updated successfully.');
    }

    /**
     * Replace the import's expense rows safely.
     * - Drops empty rows (no category AND no cbf_cbm AND no amount).
     * - Dedupes by category_id (last row wins).
     * - Ensures the category belongs to the current business and is active.
     */
    protected function syncExpenses(Import $import, array $rows, int $businessId): void
    {
        // Keep rows that have a category and at least ONE non-zero value
        $clean = collect($rows)->filter(function ($r) {
            if (empty($r['category_id'])) {
                return false;
            }
            $amount = (float) ($r['amount'] ?? 0);
            $cbfCbm = (float) ($r['cbf_cbm'] ?? 0);
            return $amount > 0 || $cbfCbm > 0;
        })->keyBy('category_id')->values();

        // Validate categories belong to this business & are active
        $validIds = Category::where('business_id', $businessId)->whereIn('id', $clean->pluck('category_id'))
            ->where('status', true)->pluck('id')->all();

        // Wipe old rows, then recreate
        $import->expenses()->delete();

        foreach ($clean as $row) {
            if (!in_array($row['category_id'], $validIds)) {
                continue;
            }

            $import->expenses()->create([
                'category_id' => $row['category_id'],
                'amount' => $row['amount'] ?? 0,
                'cbf_cbm' => $row['cbf_cbm'] ?? 0,
            ]);
        }
    }

    public function destroy($id)
    {
        $import = Import::forCurrentBusiness()->findOrFail($id);

        $import->expenses()->delete();
        $import->delete();

        return redirect()->route('backend.admin.imports.index')->with('success', 'Import record deleted successfully.');
    }
}