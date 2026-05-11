<?php

namespace App\Http\Controllers\Backend\Pos;

use App\Http\Controllers\Controller;
use App\Models\OrderLog;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;


class OrderLogController extends Controller
{
    public function index(Request $request)
    {
        // Scope to current business
        $businessId = session('business_id');

        if ($request->ajax()) {
            $logs = OrderLog::with('order')
                ->where('busniess_id', $businessId)
                ->select('order_logs.*');

            return DataTables::of($logs)
                ->addIndexColumn()

                ->addColumn('order_ref', function ($data) {
                    $ref = $data->order?->reference ?? '#' . $data->order_id;
                    return '<span class="fw-semibold">' . e($ref) . '</span>';
                })

                ->addColumn('status_code', function ($data) {
                    $color = match (true) {
                        $data->status_code >= 200 && $data->status_code < 300 => 'success',
                        $data->status_code >= 400 && $data->status_code < 500 => 'warning',
                        $data->status_code >= 500                             => 'danger',
                        default                                               => 'secondary',
                    };
                    return '<span class="badge bg-' . $color . ' bg-opacity-10 text-' . $color . '">'
                        . $data->status_code . '</span>';
                })

                ->addColumn('success', function ($data) {
                    return $data->success
                        ? '<span class="badge bg-success bg-opacity-10 text-success">Success</span>'
                        : '<span class="badge bg-danger bg-opacity-10 text-danger">Failed</span>';
                })

                ->addColumn('error', function ($data) {
                    if (!$data->error_message) {
                        return '<span class="text-muted small">—</span>';
                    }
                    return '<span class="text-danger small" title="' . e($data->error_message) . '">'
                        . e(\Str::limit($data->error_message, 40)) . '</span>';
                })

                ->addColumn('action', function ($data) {
                    return '
                <button type="button"
                    class="btn btn-sm btn-outline-primary btn-view-log"
                    data-id="' . $data->id . '"
                    data-request=\'' . json_encode($data->request_payload) . '\'
                    data-response=\'' . json_encode($data->response) . '\'
                    data-error="' . e($data->error_message) . '"
                    data-code="' . $data->error_code . '">
                    <i class="fas fa-eye"></i> View
                </button>';
                })

                ->editColumn('created_at', function ($row) {
                    return \Carbon\Carbon::parse($row->created_at)->format('Y-m-d');
                })

                ->rawColumns(['order_ref', 'status_code', 'success', 'error', 'action'])
                ->make(true);
        }

        return view('backend.order_logs.index');
    }
}
