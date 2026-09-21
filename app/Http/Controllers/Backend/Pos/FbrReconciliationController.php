<?php

namespace App\Http\Controllers\Backend\Pos;

use App\Http\Controllers\Controller;
use App\Imports\FbrLedgerImport;
use App\Models\Order;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class FbrReconciliationController extends Controller
{
    public function index()
    {
        return view('backend.orders.fbr-reconciliation.index');
    }

    public function compare(Request $request)
    {
        $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'ledger_file' => ['required', 'file', 'mimes:xls,xlsx,csv'],
        ]);

        $businessId = session('business_id');
        if (!$businessId) {
            return response()->json(['error' => 'No business selected'], 422);
        }

        // Unique invoice numbers present in the uploaded FBR file
        $import = new FbrLedgerImport();
        Excel::import($import, $request->file('ledger_file'));
        $fileInvoiceNumbers = array_keys($import->invoiceNumbers);

        if (empty($fileInvoiceNumbers)) {
            return response()->json([
                'error' => 'Could not find an "Invoice No." column with data in the uploaded file.',
            ], 422);
        }

        // System orders in the date range that were submitted to FBR
        $orders = Order::where('business_id', $businessId)
            ->whereNotNull('fbr_invoice_no')
            ->whereBetween('order_date', [$request->from_date, $request->to_date])
            ->get(['id', 'invoice_number', 'fbr_invoice_no', 'order_date', 'total'])
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'invoice_number' => $order->invoice_number,
                    'fbr_invoice_no' => $order->fbr_invoice_no,
                    'order_date' => \Carbon\Carbon::parse($order->order_date)->format('Y-m-d'),
                    'total' => $order->total,
                ];
            });

        // In system but NOT in the uploaded file
        $missing = $orders->filter(
            fn($order) => !in_array($order['fbr_invoice_no'], $fileInvoiceNumbers, true)
        )->values();

        return response()->json([
            'total_orders_checked' => $orders->count(),
            'missing_count' => $missing->count(),
            'missing_invoices' => $missing,
        ]);
    }
}