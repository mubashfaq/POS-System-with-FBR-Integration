<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 8mm 6mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            color: #212529;
            width: 100%;
        }

        .invoice-page {
            page-break-after: always;
            padding: 0;
        }

        .invoice-page:last-child {
            page-break-after: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        table th,
        table td {
            border-top: 1px solid #dee2e6;
            padding: 6px 8px;
            text-align: left;
            font-size: 11px;
        }

        table thead th {
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        h2 {
            margin: 0;
            font-size: 20px;
        }

        h4 {
            margin: 0;
            font-size: 18px;
        }

        .row {
            display: table;
            width: 100%;
        }

        .col {
            display: table-cell;
            vertical-align: top;
        }

        .summary-table {
            width: 50%;
            margin-left: 50%;
        }

        .note {
            color: #6c757d;
            margin-top: 10px;
            font-size: 11px;
        }

        .fbr-row {
            margin-top: 15px;
        }

        .fbr-row .col {
            vertical-align: middle;
        }
    </style>
</head>

<body>
    @foreach ($invoices as $data)
        @php
            $order = $data['order'];
            $customFields = $data['customFields'];
            $qrCode = $data['qrCode'];
            $fbrImage = $data['fbrImage'];
        @endphp
        <div class="invoice-page">

            <!-- Title row -->
            <div class="row">
                <div class="col" style="width:34%;">
                    <h2>
                        @if($logoBase64)
                            <img src="{{ $logoBase64 }}" height="40" width="40" alt="Logo">
                        @endif
                        @if(readConfig('is_show_site_invoice')) {{ $business->business_name }} @endif
                    </h2>
                </div>
                <div class="col text-center" style="width:32%;">
                    <h4>Invoice</h4>
                </div>
                <div class="col text-right" style="width:34%;">
                    @if(($invoiceSettings?->date_on_top ?? 0) == 1)
                        <small>Date: {{ date('d-M-Y') }}</small>
                    @endif
                </div>
            </div>

            <!-- Info row -->
            <div class="row" style="margin-top:15px;">
                <div class="col" style="width:34%;">
                    @if(readConfig('is_show_customer_invoice'))
                        To<br>
                        <strong>Name: {{ $order->customer->name ?? "N/A" }}</strong><br>
                        Address: {{ $order->customer->address ?? "N/A" }}<br>
                        Phone: {{ $order->customer->phone ?? "N/A" }}<br>
                        NTN/CNIC: {{ $order->customer->ntn_cnic ?? "N/A" }}<br>
                    @endif
                </div>
                <div class="col" style="width:32%;">
                    From<br>
                    @if(readConfig('is_show_site_invoice')) <strong>Name: {{ $business->business_name }}</strong><br> @endif
                    @if(readConfig('is_show_address_invoice')) Address: {{ $business->address }}<br> @endif
                    @if(readConfig('is_show_phone_invoice')) Phone: {{ $business->phone }}<br> @endif
                    @if(readConfig('is_show_email_invoice')) Email: {{ $business->email }}<br> @endif
                    @if ($business->business_type == 'pral')
                        POSID: {{ $order->POSID ?: $business->ntn }}
                    @else
                        NTN/CNIC: {{ $business->ntn ?: $business->cnic }}
                    @endif
                </div>
                <div class="col" style="width:34%;">
                    Info<br>
                    Invoice No. {{ $order->display_invoice_number }}<br>
                    @if(($invoiceSettings?->po_number ?? 0) == 1)
                        PO No. {{ $order->po_number ?? '-' }}<br>
                    @endif
                    Sale Date: {{ date('d-M-Y', strtotime($order->order_date ?? $order->created_at)) }}<br>
                    @if (($fbrIntegration ?? null) == 'enabled' && $order->order_stage !== 'draft')
                        FBR Invoice: {{ $order->fbr_invoice_no }}
                    @endif
                </div>
            </div>

            <!-- Items table -->
            <table class="items">
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>Product</th>
                        @foreach ($customFields as $field)
                            <th>{{ $field->field_label }}</th>
                        @endforeach
                        @if ($order->type !== 'pos_invoice')
                            <th>HS Code</th>
                            <th>UOM</th>
                        @endif
                        <th>Quantity</th>
                        @if(($invoiceSettings?->unit_price ?? 0) == 1)
                            <th>Unit Price {{ currency()->symbol ?? '' }}</th>
                        @endif
                        <th>Sales Excl. Tax {{ currency()->symbol ?? '' }}</th>
                        <th>Sales Tax {{ currency()->symbol ?? '' }}</th>
                        <th>Sales Incl. Tax {{ currency()->symbol ?? '' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalExclTax = 0;
                        $totalSalesTax = 0;
                        $totalFurtherTax = 0;
                        $totalInclTax = 0;
                    @endphp
                    @foreach ($order->products->where('is_accessory', 0) as $item)
                        <tr>
                            <td>{{ $loop->index + 1 }}</td>
                            <td>
                                {{ $item->product->name }}
                                @if ($item->variation)
                                    <br>
                                    <small>{{ $item->variation->label }}</small>
                                @endif
                            </td>
                            @foreach ($customFields as $field)
                                <td>{{ $item->custom_fields[$field->field_name] ?? '—' }}</td>
                            @endforeach
                            @if ($order->type !== 'pos_invoice')
                                <td>{{ $item->hsCode }}</td>
                                <td>{{ $item->uom }}</td>
                            @endif
                            <td>{{ $item->quantity }} {{ optional($item->product->unit)->short_name }}</td>
                            @if(($invoiceSettings?->unit_price ?? 0) == 1)
                                <td>{{ $item->price }}</td>
                            @endif
                            @php
                                $inclTax = $item->total;
                                $exclTax = $item->sub_total;
                                if (!is_null($item->sale_tax) || !is_null($item->further_tax)) {
                                    $taxAmount = $item->sale_tax;
                                    $furtherTax = $item->further_tax;
                                } else {
                                    $taxRate = $order->type == 'pos_invoice'
                                        ? $item->product->pos_sale_tax
                                        : $item->product->rate_of_tax;
                                    $taxRate = floatval(str_replace('%', '', $taxRate));
                                    $taxAmount = $item->sub_total * ($taxRate / 100);
                                    $furtherTax = 0;
                                    if (
                                        $order->customer->is_registered &&
                                        strtolower($order->customer->is_registered) === 'unregistered' &&
                                        $item->product->sale_type != '3rd Schedule Goods'
                                    ) {
                                        $furtherTax = $exclTax * 0.04;
                                    }
                                }
                                $totalExclTax += $exclTax;
                                $totalSalesTax += $taxAmount;
                                $totalFurtherTax += $furtherTax;
                                $totalInclTax += $inclTax;
                            @endphp
                            <td>{{ number_format($exclTax, 2) }}</td>
                            <td>{{ number_format($taxAmount, 2) }}</td>
                            <td>{{ number_format($inclTax, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Totals -->
            <table class="summary-table">
                <tr>
                    <th style="width:50%">Sales Excl. Tax:</th>
                    <td class="text-right">{{ currency()->symbol . ' ' . number_format($totalExclTax, 2, '.', ',') }}</td>
                </tr>
                @if($order->type == 'pos_invoice')
                    <tr>
                        <th>Sales Tax:</th>
                        <td class="text-right">{{ currency()->symbol . ' ' . number_format($totalSalesTax, 2, '.', ',') }}</td>
                    </tr>
                @else
                    <tr>
                        <th>Sales Tax:</th>
                        <td class="text-right">{{ currency()->symbol . ' ' . number_format($totalSalesTax, 2, '.', ',') }}</td>
                    </tr>
                    <tr>
                        <th>Further Tax:</th>
                        <td class="text-right">{{ currency()->symbol . ' ' . number_format($totalFurtherTax, 2, '.', ',') }}
                        </td>
                    </tr>
                @endif
                <tr>
                    <th>Sales Incl. Tax:</th>
                    <td class="text-right">{{ currency()->symbol . ' ' . number_format($totalInclTax, 2, '.', ',') }}</td>
                </tr>
                <tr>
                    <th>Paid:</th>
                    <td class="text-right">{{ currency()->symbol . ' ' . number_format($order->paid, 2, '.', ',') }}</td>
                </tr>
                <tr>
                    <th>Due:</th>
                    <td class="text-right">{{ currency()->symbol . ' ' . number_format($order->due, 2, '.', ',') }}</td>
                </tr>
            </table>

            <!-- Thanks note -->
            @if(!empty($invoiceSettings->thanks_for_shopping) && $invoiceSettings->thanks_for_shopping == 1)
                <p class="note">
                    @if(readConfig('is_show_note_invoice')) {{ readConfig('note_to_customer_invoice') }} @endif
                </p>
            @endif

            <!-- QR code / FBR / IMC logo row -->
            <div class="row fbr-row">
                @if (($fbrIntegration ?? null) == 'enabled' && $order->order_stage !== 'draft')
                    <div class="col" style="width:15%;">
                        @if($qrCode)
                            <img src="data:image/svg+xml;base64,{{ $qrCode }}" width="60" height="60" alt="QR">
                        @endif
                    </div>
                    <div class="col" style="width:15%;">
                        @if($fbrImage)
                            <img src="{{ $fbrImage }}" style="width:100%;" alt="FBR">
                        @endif
                    </div>
                @else
                    <div class="col" style="width:30%;"></div>
                @endif

                <div class="col" style="width:55%;"></div>

                @if($imcLogoBase64)
                    <div class="col" style="width:15%;">
                        <img src="{{ $imcLogoBase64 }}" style="width:100%;" alt="">
                    </div>
                @endif
            </div>

        </div>
    @endforeach
</body>

</html>