<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Receipts</title>
    <style>
        @font-face {
            font-family: 'Cousine';
            font-style: normal;
            font-weight: normal;
            src: url('{{ public_path('fonts/Cousine-Regular.ttf') }}') format('truetype');
        }

        @font-face {
            font-family: 'Cousine';
            font-style: normal;
            font-weight: bold;
            src: url('{{ public_path('fonts/Cousine-Bold.ttf') }}') format('truetype');
        }

        @page {
            margin: 20px;
        }

        body,
        table,
        td,
        th,
        div,
        p,
        h3,
        h6,
        address,
        small {
            font-family: 'Cousine', 'Courier', monospace;
        }

        body {
            font-size: 12px;
            line-height: 1.5;
            margin: 0;
            color: #212529;
        }

        /* Centering wrapper */
        .page-wrap {
            width: 100%;
        }

        .page-break {
            page-break-after: always;
        }

        .receipt {
            border: 1px dotted #000;
            padding: 8px;
            text-align: left;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .left {
            text-align: left;
        }

        .mt-1 {
            margin-top: 4px;
        }

        /* Bootstrap equivalents */
        p {
            margin: 0 0 16px 0;
        }

        h6 {
            font-size: 16px;
            font-weight: 500;
            line-height: 1.2;
            margin: 0 0 8px 0;
        }

        h3 {
            font-size: 28px;
            font-weight: 500;
            line-height: 1.2;
            margin: 0 0 8px 0;
        }

        small {
            font-size: 80%;
        }

        address {
            margin: 0 0 16px 0;
            font-style: normal;
        }

        hr {
            border: none;
            border-top: 1px dashed #000;
            margin: 5px 0;
            height: 0;
        }

        table.items,
        table.sum {
            width: 100%;
            border-collapse: collapse;
        }

        td,
        th {
            padding: 2px 0;
            vertical-align: top;
        }
    </style>
</head>

<body>

    @foreach ($receipts as $r)
        @php $order = $r['order']; @endphp

        <div class="page-wrap {{ $loop->last ? '' : 'page-break' }}">
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td align="center" style="padding:0;">

                        <div class="receipt" style="width: {{ $maxWidth }};">

                            {{-- ================= HEADER ================= --}}
                            <div class="center">
                                @if ($logoBase64)
                                    @if ($r['isPos'])
                                        <img src="{{ $logoBase64 }}" height="100" width="100" alt="Logo"><br>
                                    @else
                                        <img src="{{ $logoBase64 }}" height="50" width="70" alt="Logo"><br>
                                    @endif
                                @endif

                                @if (!empty($invoiceSettings->site_name) && $invoiceSettings->site_name == 1)
                                    @if ($r['isPos'])
                                        <h6 class="mt-1">{{ $business->business_name ?? '' }}</h6>
                                    @else
                                        <h3>{{ $business->business_name ?? '' }}</h3>
                                    @endif
                                @endif

                                @if (!empty($invoiceSettings->address) && $invoiceSettings->address == 1)
                                    <div class="mt-1">{{ $business->address ?? '' }}</div>
                                @endif

                                @if ($r['isPos'])
                                    @if ($isPral)
                                        <div class="mt-1">POSID: {{ $order->POSID ?? $business->ntn }}</div>
                                    @else
                                        <div class="mt-1">NTN: {{ $business->ntn ?? 'NTN' }}</div>
                                    @endif
                                @else
                                    @if (!empty($invoiceSettings->phone) && $invoiceSettings->phone == 1)
                                        {{ $business->phone ?? '' }}<br>
                                    @endif
                                @endif

                                @if (!empty($invoiceSettings->email) && $invoiceSettings->email == 1)
                                    {{ $business->email ?? '' }}<br>
                                @endif

                                <div class="mt-1">
                                    <p>{{ date('d-M-Y') }} {{ date('h:i:s A') }}</p>
                                </div>
                            </div>

                            User: {{ $userName }}<br>
                            @unless ($r['isPos'])
                                Order: #{{ $order->id }}<br>
                                <hr>
                            @endunless

                            {{-- ================= CUSTOMER ================= --}}
                            @if ($showCustomer)
                                <address class="left">
                                    @if ($r['isPos'])
                                        Customer : {{ $order->customer->name ?? 'N/A' }}<br>
                                    @else
                                        Name: {{ $order->customer->name ?? 'N/A' }}<br>
                                        Address: {{ $order->customer->address ?? 'N/A' }}<br>
                                        Phone: {{ $order->customer->phone ?? 'N/A' }}<br>
                                    @endif
                                    Sale Date: {{ date('d/m/Y', strtotime($order->order_date ?? $order->created_at)) }}
                                </address>
                            @endif
                            <hr>

                            {{-- ================= ITEMS ================= --}}
                            <table class="items">
                                <thead>
                                    <tr>
                                        <th class="left">Product</th>
                                        <th class="right"></th>
                                        <th class="right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($r['lines'] as $line)
                                        <tr>
                                            <td>
                                                {{ $line['name'] }}
                                                @if ($line['variation'])
                                                    <br><small>{{ $line['variation'] }}</small>
                                                @endif
                                            </td>
                                            <td class="right">
                                                {{ $line['qty'] }} *
                                                {{ $r['isPos'] ? number_format($line['price'], 2) : $line['price'] }}
                                            </td>
                                            <td class="right">{{ number_format($line['total'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <hr>

                            {{-- ================= SUMMARY ================= --}}
                            @if ($r['isPos'])
                                <table class="sum">
                                    <tr>
                                        <td class="right">Subtotal (Excl. Tax):
                                            {{ number_format($order->sub_total - $r['totalTax'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="right">Sales Tax: {{ number_format($r['totalTax'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="right">Discount: {{ number_format($order->discount, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="right">PoS Fee: 1.00</td>
                                    </tr>
                                    <tr>
                                        <td class="right">Total:
                                            <strong>{{ number_format($order->total + 1, 2) }}</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="right">Due: {{ number_format($order->due, 2) }}</td>
                                    </tr>
                                </table>
                            @else
                                <table class="sum">
                                    <tr>
                                        <td>Subtotal (Incl. Tax):</td>
                                        <td class="right">{{ number_format($order->sub_total, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Discount:</td>
                                        <td class="right">{{ number_format($order->discount, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total:</strong></td>
                                        <td class="right"><strong>{{ number_format($order->total, 2) }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td>Paid:</td>
                                        <td class="right">{{ number_format($order->paid, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Due:</td>
                                        <td class="right">{{ number_format($order->due, 2) }}</td>
                                    </tr>
                                </table>
                            @endif
                            <hr>

                            {{-- ================= FOOTER / FBR ================= --}}
                            <div class="center">
                                @if (($fbrIntegration ?? null) == 'enabled')
                                    @if ($order->order_stage !== 'draft')
                                        <h6>FBR invoice #</h6>
                                        <h6>{{ $order->fbr_invoice_no }}</h6>

                                        <table style="width:auto; margin: 8px auto 0; border-collapse:collapse;">
                                            <tr>
                                                <td style="padding-right: 10px; vertical-align: middle;">
                                                    @if ($r['qrCode'])
                                                        <img src="data:image/svg+xml;base64,{{ $r['qrCode'] }}"
                                                            width="60" height="60" alt="QR">
                                                    @endif
                                                </td>
                                                <td style="vertical-align: middle;">
                                                    @if ($r['fbrImage'])
                                                        <img src="{{ $r['fbrImage'] }}"
                                                            style="width: {{ $isPral ? '110px' : '70px' }};"
                                                            alt="FBR">
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>

                                        <p class="mt-1" style="margin-top: 8px;">Verify this invoice through FBR
                                            TaxAsaan MobileApp or SMS at 9966 and win exciting prizes in draw</p>
                                    @endif
                                @else
                                    <p class="mt-1">FBR is not integrated.</p>
                                @endif

                                <p>POS software by techuick</p>

                                @if (!empty($invoiceSettings->note_to_customer) && $invoiceSettings->note_to_customer == 1)
                                    <p style="font-size: 12px;">{{ $invoiceSettings->note_msg ?? '' }}</p>
                                @endif
                            </div>
                        </div>

                    </td>
                </tr>
            </table>
        </div>
    @endforeach

</body>

</html>
