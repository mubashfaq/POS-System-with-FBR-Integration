<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\ProductLog;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class ProductLogController extends Controller
{
    public function index(Request $request)
    {

        $businessId = session('business_id');

        if ($request->ajax()) {
            
            $logs = ProductLog::with(['user:id,name', 'product:id,name,sku'])->where('business_id', $businessId)
                ->select('product_logs.*')->latest();


            return DataTables::of($logs)
                ->addIndexColumn()

                ->addColumn('product', function ($data) {
                    $name = $data->product_name ?? $data->product?->name ?? '(deleted)';
                    $sku = $data->product_sku ?? $data->product?->sku;
                    return '<span class="fw-semibold">' . e($name) . '</span>'
                        . ($sku ? '<br><small class="text-muted">' . e($sku) . '</small>' : '');
                })

                ->addColumn('user', fn($data) => e($data->user?->name ?? 'System'))

                ->addColumn('action_badge', function ($data) {
                    $map = [
                        'created' => ['success', 'Created'],
                        'updated' => ['warning', 'Updated'],
                        'deleted' => ['danger', 'Deleted'],
                    ];
                    [$color, $label] = $map[$data->action] ?? ['secondary', ucfirst($data->action)];
                    return '<span class="badge bg-' . $color . ' bg-opacity-10 text-' . $color . '">' . $label . '</span>';
                })

                ->addColumn('changes_summary', function ($data) {
                    if ($data->action === 'deleted') {
                        return '<span class="text-muted small">Product removed</span>';
                    }
                    $fields = $data->changed_fields ? array_keys($data->changed_fields) : [];
                    if (empty($fields)) {
                        return '<span class="text-muted small">No field changes</span>';
                    }
                    return collect($fields)
                        ->map(fn($f) => '<span class="badge bg-light text-dark me-1">' . e($f) . '</span>')
                        ->implode('');
                })

                ->addColumn('action', function ($data) {
                    return '
                        <button type="button"
                            class="btn btn-sm btn-outline-primary btn-view-product-log"
                            data-id="' . $data->id . '"
                            data-old=\'' . json_encode($data->old_values) . '\'
                            data-new=\'' . json_encode($data->new_values) . '\'
                            data-changed=\'' . json_encode($data->changed_fields) . '\'
                            data-action="' . $data->action . '">
                            <i class="fas fa-eye"></i> View
                        </button>';
                })

                ->editColumn('created_at', fn($row) => $row->created_at->format('Y-m-d H:i'))

                ->rawColumns(['product', 'user', 'action_badge', 'changes_summary', 'action'])
                ->make(true);
        }

        return view('backend.logs.product-log');
    }
}