<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Orders Report</title>
    <style>
        body  { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
        h2    { text-align: center; margin-bottom: 4px; }
        p.sub { text-align: center; color: #666; margin-top: 0; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #343a40; color: #fff;
            padding: 7px 6px; text-align: left;
        }
        tbody tr:nth-child(even) { background: #f8f9fa; }
        tbody td { padding: 6px; border-bottom: 1px solid #dee2e6; }
        .badge-paid   { color: #155724; background:#d4edda; padding:2px 7px; border-radius:4px; }
        .badge-due    { color: #721c24; background:#f8d7da; padding:2px 7px; border-radius:4px; }
        .badge-pos    { color: #0c5460; background:#d1ecf1; padding:2px 7px; border-radius:4px; }
        .badge-digital{ color: #383d41; background:#e2e3e5; padding:2px 7px; border-radius:4px; }
        .text-right   { text-align: right; }
        footer { margin-top: 20px; font-size: 10px; color: #888; text-align: right; }
    </style>
</head>
<body>

    <h2>Orders Report</h2>
    <p class="sub">Generated: {{ now()->format('Y-m-d H:i') }} &nbsp;|&nbsp; Total Orders: {{ $orders->count() }}</p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Sale ID</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Sub Total</th>
                <th>Discount</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Due</th>
                <th>Type</th>
                <th>Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orders as $i => $order)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>#{{ $order->id }}</td>
                <td>{{ $order->customer->name ?? '-' }}</td>
                <td>{{ number_format($order->total_item, 2) }}</td>
                <td>{{ number_format($order->sub_total, 2) }}</td>
                <td>{{ number_format($order->discount, 2) }}</td>
                <td>{{ number_format($order->total, 2) }}</td>
                <td>{{ number_format($order->paid, 2) }}</td>
                <td>{{ number_format($order->due, 2) }}</td>
                <td>
                    @if($order->type == 'pos_invoice')
                        <span class="badge-pos">POS</span>
                    @else
                        <span class="badge-digital">Digital</span>
                    @endif
                </td>
                <td>{{ $order->created_at->format('Y-m-d') }}</td>
                <td>
                    @if($order->status)
                        <span class="badge-paid">Paid</span>
                    @else
                        <span class="badge-due">Due</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <footer>Printed by {{ config('app.name') }}</footer>
</body>
</html>