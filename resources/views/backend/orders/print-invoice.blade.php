@extends('backend.master')
@section('title', $order->display_invoice_number)
@section('content')
<div class="card">
    @if(($invoiceSettings?->invoice_number ?? 0) == 1)
        <div class="mt-n5 mb-3 d-flex justify-content-end align-items-center">
            <form id="customInvoiceForm" class="d-flex align-items-center">
                @csrf
                <input type="hidden" id="order_id" value="{{ $order->id }}">

                <div class="input-group input-group-sm" style="width: 340px;">
                    <input
                        type="text"
                        class="form-control"
                        id="custom_invoice_number"
                        name="custom_invoice_number"
                        placeholder="Custom Invoice Number"
                        value="{{ $order->custom_invoice_number }}"
                    >

                    <div class="input-group-append">
                        <button type="submit" class="btn action-button">
                            <i class="fas fa-save"></i> Save
                        </button>
                    </div>
                </div>

                <small id="invoice-message" class="text-success ml-2"></small>
            </form>
        </div>
    @endif
    <div class="card-body">
        <!-- Main content -->
        <section class="invoice">
            <!-- title row -->
            <div class="row mb-4">
                <div class="col-4">
                    <h2 class="page-header">
                        @if(readConfig('is_show_logo_invoice'))
                            @if ( !empty($business->logo))
                                <img src="{{asset('storage/'.$business->logo)}}" height="40" width="40" alt="Logo"
                                class="brand-image img-circle elevation-3" style="opacity: .8">
                            @else
                                <img src="{{ asset('storage/logos/default-business.png') }}" height="40" width="40" alt="Logo"
                                class="brand-image img-circle elevation-3" style="opacity: .8">
                            @endif
                        @endif
                        @if(readConfig('is_show_site_invoice')){{ $business->business_name }} @endif
                    </h2>
                </div>
                <div class="col-4">
                    <h4 class="page-header" align="center">Invoice</h4>
                </div>
                <div class="col-4">
                    <small class="float-right text-small">Date: {{date('d/m/Y')}}</small>
                </div>
                <!-- /.col -->
            </div>
            <!-- info row -->
            <div class="row invoice-info">
                <!-- /.col -->
                <div class="col-sm-4 invoice-col">
                    @if(readConfig('is_show_customer_invoice'))
                    To
                    <address>
                        <strong>Name: {{$order->customer->name??"N/A"}}</strong><br>
                        Address: {{$order->customer->address??"N/A"}}<br>
                        Phone: {{$order->customer->phone??"N/A"}}<br>
                        NTN/CNIC: {{$order->customer->ntn_cnic??"N/A"}}<br>
                    </address>
                    @endif
                </div>
                <div class="col-sm-4 invoice-col">
                    From
                    <address>
                        <!-- @if(readConfig('is_show_site_invoice'))<strong>Name:{{ readConfig('site_name') }}</strong><br> @endif
            @if(readConfig('is_show_address_invoice'))Address: {{ readConfig('contact_address') }}<br>@endif
            @if(readConfig('is_show_phone_invoice'))Phone: {{ readConfig('contact_phone') }}<br>@endif
            @if(readConfig('is_show_email_invoice'))Email: {{ readConfig('contact_email') }}<br>@endif -->

                        @if(readConfig('is_show_site_invoice'))<strong>Name:{{ $business->business_name }}</strong><br>
                        @endif
                        @if(readConfig('is_show_address_invoice'))Address: {{ $business->address }}<br>@endif
                        @if(readConfig('is_show_phone_invoice'))Phone: {{ $business->phone }}<br>@endif
                        @if(readConfig('is_show_email_invoice'))Email: {{ $business->email }}<br>@endif
                            NTN/CNIC: {{ ($business->ntn) ? $business->ntn :$business->cnic }}
                    </address>
                </div>
                <!-- /.col -->
                <div class="col-sm-4 invoice-col">
                    Info <br>
                    Sale ID #{{$order->id}}<br>
                    Sale Date: {{date('d/m/Y', strtotime($order->created_at))}}<br>
                    @if (($fbrIntegration ?? null) == 'enabled' && $order->order_stage !== 'draft')
                        FBR Invoice: <div>{{ $order->fbr_invoice_no }}</div>
                    @endif
                </div>
                <!-- /.col -->
            </div>
            <!-- /.row -->

            <!-- Table row -->
            <div class="row">
                <div class="col-12 table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>SN</th>
                                <th>Product</th>
                                @if ($order->type !== 'pos_invoice') 
                                <th>HS Code</th>
                                <th>UOM</th>
                                @endif
                                <th>Quantity</th>
                                <th>Unit Price {{currency()->symbol??''}}</th>
                                <th>Sales Excl. Tax {{currency()->symbol??''}}</th>
                                <th>Sales Tax {{currency()->symbol??''}}</th>
                                <th>Sales Incl. Tax {{currency()->symbol??''}}</th>
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
                                <td>{{$loop->index + 1}}</td>
                                <td>{{$item->product->name}}</td>
                                @if ($order->type !== 'pos_invoice') 
                                <td>{{$item->product->hsCode}}</td>
                                <td>{{$item->product->uom}}</td>
                                @endif
                                <td>{{$item->quantity}} {{optional($item->product->unit)->short_name}}</td>
                                <td>
                                    {{$item->price }}
                                    {{-- @if ($item->price>$item->discounted_price)
                                    <br><del>{{ $item->price }}</del>
                                    @endif --}}
                                </td>
                                @php
                                    $inclTax = $item->total;
                                    $exclTax = $item->sub_total;
                                    if(!is_null($item->sale_tax) || !is_null($item->further_tax)){
                                        $taxAmount = $item->sale_tax;
                                        $furtherTax = $item->further_tax;
                                    } else {
                                        if($order->type == 'pos_invoice'){
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
                                    

                                    // ✅ Accumulate into totals
                                    $totalExclTax    += $exclTax;
                                    $totalSalesTax  += $taxAmount;
                                    $totalFurtherTax += $furtherTax;
                                    $totalInclTax    += $inclTax;
                                @endphp

                                <td>{{ number_format($exclTax, 2) }}</td>
                                <td>{{ number_format($taxAmount, 2) }}</td>
                                <td>{{ number_format($inclTax, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- /.col -->
            </div>
            <!-- /.row -->

            <div class="row">
                <!-- accepted payments column -->
                <div class="col-6">

                </div>

                <!-- /.col -->
                <div class="col-6">

                    <div class="table-responsive">
                        <table class="table">
                            <tr>
                                <th style="width:50%">Sales Excl. Tax:</th>
                                <td class="text-right">
                                    {{currency()->symbol.' '.number_format($totalExclTax,2,'.',',')}}</td>
                            </tr>

                            @if($order->type == 'pos_invoice')
                            <tr>
                                <th>Sales Tax:</th>
                                <td class="text-right">
                                    {{currency()->symbol.' '.number_format($totalSalesTax,2,'.',',') }}</td>
                            </tr>
                            @endif 

                            @if($order->type !== 'pos_invoice')
                             <tr>
                                <th>Sales Tax:</th>
                                <td class="text-right">
                                    {{ currency()->symbol.' '.number_format($totalSalesTax, 2, '.', ',') }}
                                </td>
                            </tr>
                            <tr>
                                <th>Further Tax:</th>
                                <td class="text-right">
                                    {{currency()->symbol.' '.number_format($totalFurtherTax,2,'.',',')}}</td>
                            </tr>
                            @endif

                            <tr>
                                <th>Sales Incl. Tax:</th>
                                <td class="text-right">{{currency()->symbol.' '.number_format($totalInclTax,2,'.',',')}}
                                </td>
                            </tr>
                            <tr>
                                <th>Paid:</th>
                                <td class="text-right">{{currency()->symbol.' '.number_format($order->paid,2,'.',',')}}
                                </td>
                            </tr>
                            <tr>
                                <th>Due:</th>
                                <td class="text-right">{{currency()->symbol.' '.number_format($order->due,2,'.',',')}}
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <!-- /.col -->
            </div>
            <div class="row">
                <!-- accepted payments column -->
                <div class="col-6">
                    @if(!empty($invoiceSettings->thanks_for_shopping) && $invoiceSettings->thanks_for_shopping == 1)
                        <p class="text-muted well well-sm shadow-none" style="margin-top: 10px;">
                            @if(readConfig('is_show_note_invoice')){{ readConfig('note_to_customer_invoice') }}@endif
                        </p>
                    @endif
                </div>
            </div>
            <div class="row">
                @if (($fbrIntegration ?? null) == 'enabled' && $order->order_stage !== 'draft')

                    <div class="col-md-1 mt-2">
                        <!-- <h3>FBR Dig</h3> -->
                        <div id="qrcode"></div>
                        
                    </div>

                    <div class="col-md-1">
                        <div class="img">
                            @if ($business->business_type == 'pral')
                                <img src="{{ asset('assets/images/pral-pos-component.png') }}" style="width: 120%;" alt="PRA Invoice">
                            @elseif($order->type == 'pos_invoice')
                                <img src="{{ asset('assets/images/fbr_pos_invoice_system.png') }}" style="width: 100%;" alt="POS Invoice">
                            @else
                                <img src="{{ asset('assets/images/fbr_logo-icon.png') }}" style="width: 100%;" alt="Digital Invoice">
                            @endif
                        </div>
                    </div>
                @else
                    <div class="col-md-2"></div>
                @endif
              <div class="col-md-8"></div>
              
                @if(!empty($invoiceSettings->imc_logo) && $invoiceSettings->imc_logo == 1)
                    <div class="col-md-2">
                        <div class="img">
                            <img src="{{ asset('assets/images/imc.jpeg') }}" style="width: 100%;" alt="">
                        </div>
                    </div>
                @endif

            </div>
            <div class="row no-print">
                <div class="col-12">
                    <button type="button" onclick="window.print()" class="btn btn-success float-right"><i
                            class="fas fa-print"></i> Print</a>
                    </button>
                </div>
            </div>
            <!-- /.row -->
        </section>
        <!-- /.content -->
    </div>
</div>
@endsection

@push('style')
<style>
.invoice {
    border: none !important;
}
</style>
@endpush
@push('script')

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    $(function () {

        $('#customInvoiceForm').on('submit', function (e) {
            e.preventDefault();

            let btn = $(this).find('button[type="submit"]');

            btn.prop('disabled', true);

            $.ajax({
                url: "{{ route('backend.admin.orders.custom-invoice', $order->id) }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    custom_invoice_number: $('#custom_invoice_number').val()
                },
                success: function (response) {

                    showToast(response.message, 'success');

                },
                error: function (xhr) {

                    if (xhr.status === 422) {
                        showToast(xhr.responseJSON.errors.custom_invoice_number[0], 'error');
                    } else {
                        showToast(xhr.responseJSON?.message ?? 'Something went wrong.', 'error');
                    }

                },
                complete: function () {
                    btn.prop('disabled', false);
                }
            });
        });

    });
    

    window.addEventListener("load", window.print());

    new QRCode(document.getElementById("qrcode"), {
        text: "{{ $order->fbr_invoice_no }}",
        width: 60,
        height: 60
    });
</script>
@endpush