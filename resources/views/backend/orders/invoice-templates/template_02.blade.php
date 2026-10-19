@extends('backend.master')
@section('title', $order->display_invoice_number)
@section('content')
    <div class="card modern-invoice-card">

        <div class="card-body">
            <section class="invoice invoice-modern" style="border: none;">

                <!-- Header -->
                <div class="invoice-header">
                    <div class="invoice-header-left">
                        @if (readConfig('is_show_logo_invoice'))
                            @if (!empty($business->logo))
                                <img src="{{ asset('storage/' . $business->logo) }}" alt="Logo" class="invoice-logo">
                            @else
                                <img src="{{ asset('storage/logos/default-business.png') }}" alt="Logo"
                                    class="invoice-logo">
                            @endif
                        @endif
                        @if (readConfig('is_show_site_invoice'))
                            <h2 class="invoice-business-name">{{ $business->business_name }}</h2>
                        @endif
                    </div>

                    <div class="invoice-header-right">
                        <h1 class="invoice-title">Invoice</h1>
                        <div class="invoice-meta">
                            <span>Invoice No. {{ $order->display_invoice_number }}</span>
                            <span>{{ date('d-M-Y', strtotime($order->order_date ?? $order->created_at)) }}</span>
                        </div>
                        @if (($fbrIntegration ?? null) == 'enabled' && $order->order_stage !== 'draft')
                            <div class="invoice-fbr-no">FBR Invoice: {{ $order->fbr_invoice_no }}</div>
                        @endif
                    </div>
                </div>

                <!-- Parties -->
                <div class="invoice-parties">
                    @if (readConfig('is_show_customer_invoice'))
                        <div class="party-card">
                            <span class="party-label">Billed To</span>
                            <strong class="party-name">{{ $order->customer->name ?? 'N/A' }}</strong>
                            <div class="party-detail">{{ $order->customer->address ?? 'N/A' }}</div>
                            <div class="party-detail">{{ $order->customer->phone ?? 'N/A' }}</div>
                            <div class="party-detail">NTN/CNIC: {{ $order->customer->ntn_cnic ?? 'N/A' }}</div>
                        </div>
                    @endif

                    <div class="party-card">
                        <span class="party-label">From</span>
                        @if (readConfig('is_show_site_invoice'))
                            <strong class="party-name">{{ $business->business_name }}</strong>
                        @endif
                        @if (readConfig('is_show_address_invoice'))
                            <div class="party-detail">{{ $business->address }}</div>
                        @endif
                        @if (readConfig('is_show_phone_invoice'))
                            <div class="party-detail">{{ $business->phone }}</div>
                        @endif
                        @if (readConfig('is_show_email_invoice'))
                            <div class="party-detail">{{ $business->email }}</div>
                        @endif
                        <div class="party-detail">NTN/CNIC: {{ $business->ntn ? $business->ntn : $business->cnic }}</div>
                    </div>
                </div>

                <!-- Items table -->
                <div class="invoice-table-wrap">
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>SN</th>
                                <th>Product</th>
                                @if ($order->type !== 'pos_invoice')
                                    <th>HS Code</th>
                                    <th>UOM</th>
                                @endif
                                <th>Qty</th>
                                <th class="text-right">Unit Price {{ currency()->symbol ?? '' }}</th>
                                <th class="text-right">Excl. Tax {{ currency()->symbol ?? '' }}</th>
                                <th class="text-right">Tax {{ currency()->symbol ?? '' }}</th>
                                <th class="text-right">Incl. Tax {{ currency()->symbol ?? '' }}</th>
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
                                    @if ($order->type !== 'pos_invoice')
                                        <td>{{ $item->product->hsCode }}</td>
                                        <td>{{ $item->product->uom }}</td>
                                    @endif
                                    <td>{{ $item->quantity }} {{ optional($item->product->unit)->short_name }}</td>
                                    <td class="text-right">{{ $item->price }}</td>

                                    @php
                                        $inclTax = $item->total;
                                        $exclTax = $item->sub_total;
                                        if (!is_null($item->sale_tax) || !is_null($item->further_tax)) {
                                            $taxAmount = $item->sale_tax;
                                            $furtherTax = $item->further_tax;
                                        } else {
                                            if ($order->type == 'pos_invoice') {
                                                $taxRate = $item->product->pos_sale_tax;
                                            } else {
                                                $taxRate = $item->product->rate_of_tax;
                                            }
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

                                    <td class="text-right">{{ number_format($exclTax, 2) }}</td>
                                    <td class="text-right">{{ number_format($taxAmount, 2) }}</td>
                                    <td class="text-right">{{ number_format($inclTax, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="invoice-summary">
                    <div class="invoice-summary-box">
                        <div class="summary-row">
                            <span>Sales Excl. Tax</span>
                            <span>{{ currency()->symbol . ' ' . number_format($totalExclTax, 2, '.', ',') }}</span>
                        </div>

                        @if ($order->type == 'pos_invoice')
                            <div class="summary-row">
                                <span>Sales Tax</span>
                                <span>{{ currency()->symbol . ' ' . number_format($totalSalesTax, 2, '.', ',') }}</span>
                            </div>
                        @endif

                        @if ($order->type !== 'pos_invoice')
                            <div class="summary-row">
                                <span>Sales Tax</span>
                                <span>{{ currency()->symbol . ' ' . number_format($totalSalesTax, 2, '.', ',') }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Further Tax</span>
                                <span>{{ currency()->symbol . ' ' . number_format($totalFurtherTax, 2, '.', ',') }}</span>
                            </div>
                        @endif

                        <div class="summary-row summary-total">
                            <span>Sales Incl. Tax</span>
                            <span>{{ currency()->symbol . ' ' . number_format($totalInclTax, 2, '.', ',') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Paid</span>
                            <span>{{ currency()->symbol . ' ' . number_format($order->paid, 2, '.', ',') }}</span>
                        </div>
                        <div class="summary-row summary-due">
                            <span>Due</span>
                            <span>{{ currency()->symbol . ' ' . number_format($order->due, 2, '.', ',') }}</span>
                        </div>
                    </div>
                </div>

                @if (!empty($invoiceSettings->thanks_for_shopping) && $invoiceSettings->thanks_for_shopping == 1)
                    <p class="invoice-note">
                        @if (readConfig('is_show_note_invoice'))
                            {{ readConfig('note_to_customer_invoice') }}
                        @endif
                    </p>
                @endif

                <!-- Footer: QR / brand marks -->
                <div class="invoice-footer">
                    @if (($fbrIntegration ?? null) == 'enabled' && $order->order_stage !== 'draft')
                        <div class="footer-qr">
                            <div id="qrcode"></div>
                            <div class="footer-qr-mark">
                                @if ($business->business_type == 'pral')
                                    <img src="{{ asset('assets/images/pral-pos-component.png') }}" alt="PRA Invoice">
                                @elseif($order->type == 'pos_invoice')
                                    <img src="{{ asset('assets/images/fbr_pos_invoice_system.png') }}" alt="POS Invoice">
                                @else
                                    <img src="{{ asset('assets/images/fbr_logo-icon.png') }}" alt="Digital Invoice">
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="footer-spacer"></div>

                    @if (!empty($invoiceSettings->imc_logo) && $invoiceSettings->imc_logo == 1)
                        <div class="footer-imc">
                            <img src="{{ asset('assets/images/imc.jpeg') }}" alt="">
                        </div>
                    @endif
                </div>

                <div class="row no-print">
                    <div class="col-12">
                        <button type="button" onclick="window.print()" class="btn btn-success float-right">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection

@push('style')
    <style>
        .modern-invoice-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 16px rgba(0, 0, 0, 0.06);
        }

        .invoice-modern {
            font-family: 'Poppins', -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #2d2d2d;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }

        .invoice-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .invoice-logo {
            height: 44px;
            width: 44px;
            border-radius: 50%;
            object-fit: cover;
        }

        .invoice-business-name {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .invoice-header-right {
            text-align: right;
        }

        .invoice-title {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 6px;
            color: #1a73e8;
        }

        .invoice-meta {
            display: flex;
            gap: 12px;
            font-size: 13px;
            color: #777;
            justify-content: flex-end;
        }

        .invoice-fbr-no {
            font-size: 12px;
            color: #555;
            margin-top: 4px;
        }

        .invoice-parties {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .party-card {
            flex: 1;
            min-width: 220px;
            background: #f8f9fb;
            border-radius: 8px;
            padding: 14px 16px;
        }

        .party-label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #999;
            margin-bottom: 6px;
        }

        .party-name {
            font-size: 15px;
            font-weight: 500;
            display: block;
            margin-bottom: 4px;
        }

        .party-detail {
            font-size: 13px;
            color: #555;
            line-height: 1.6;
        }

        .invoice-table-wrap {
            margin-bottom: 24px;
            overflow-x: auto;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .invoice-table thead th {
            background: #1a73e8;
            color: #fff;
            padding: 10px 12px;
            text-align: left;
            font-weight: 500;
            white-space: nowrap;
        }

        .invoice-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        .invoice-table tbody tr:nth-child(even) {
            background: #fafbfc;
        }

        .text-right {
            text-align: right;
        }

        .invoice-summary {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }

        .invoice-summary-box {
            width: 320px;
            background: #f8f9fb;
            border-radius: 8px;
            padding: 16px 18px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
            color: #555;
        }

        .summary-total {
            border-top: 1px solid #e0e0e0;
            margin-top: 6px;
            padding-top: 10px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .summary-due {
            color: #d9363e;
            font-weight: 600;
        }

        .invoice-note {
            font-size: 13px;
            color: #888;
            font-style: italic;
            margin-top: 10px;
        }

        .invoice-footer {
            display: flex;
            align-items: center;
            margin-top: 20px;
        }

        .footer-qr {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .footer-qr-mark img {
            width: 90px;
        }

        .footer-spacer {
            flex: 1;
        }

        .footer-imc img {
            width: 90px;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            .modern-invoice-card {
                box-shadow: none;
            }
        }
    </style>
@endpush

@push('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        window.addEventListener("load", window.print());

        new QRCode(document.getElementById("qrcode"), {
            text: "{{ $order->fbr_invoice_no }}",
            width: 60,
            height: 60
        });
    </script>
@endpush
