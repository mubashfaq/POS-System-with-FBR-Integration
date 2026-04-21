@extends('backend.master')
@section('title', 'Invoice_'.$order->id)
@section('content')
<div class="card">
    <div class="card-body">
        <!-- Main content -->
        <section class="invoice">
            <!-- title row -->
            <div class="row mb-4">
                <div class="col-4">
                    <h2 class="page-header">
                        @if(readConfig('is_show_logo_invoice'))
                        <img src="{{asset('storage/'.$business->logo)}}" height="40" width="40" alt="Logo"
                            class="brand-image img-circle elevation-3" style="opacity: .8">
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
                <div class="col-sm-5 invoice-col">
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
                <div class="col-sm-3 invoice-col">
                    Info <br>
                    Sale ID #{{$order->id}}<br>
                    Sale Date: {{date('d/m/Y', strtotime($order->created_at))}}<br>
                    <!-- <br>
          <b>Payment Due:</b> 2/22/2014<br>
          <b>Account:</b> 968-34567 -->
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
                                <!--<th>Description</th>-->
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

                            @foreach ($order->products as $item )
                            <tr>
                                <td>{{$loop->index + 1}}</td>
                                <td>{{$item->product->name}}</td>
                                <!--<td>{{$item->product->hs_description}}</td>-->
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
                                    if($order->type == 'pos_invoice'){
                                        $taxRate = $item->product->pos_sale_tax;
                                    } else {
                                        $taxRate = $item->product->rate_of_tax;
                                    }

                                    $taxRate = floatval(str_replace('%', '', $taxRate));
                                    $taxAmount = $item->sub_total * ($taxRate / 100);
                                    $exclTax = $item->sub_total;
                                    $furtherTax = 0;

                                    if (
                                        $order->customer->is_registered &&
                                        strtolower($order->customer->is_registered) === 'unregistered' &&
                                        $item->product->sale_type != '3rd Schedule Goods'
                                    ) {
                                        $furtherTax = $exclTax * 0.04;
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
                    <!-- <p class="lead">Amount Due 2/22/2014</p> -->

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
                    <!-- <p class="lead">Payment:Cash Paid</p> -->
                    <!-- <small class="lead text-small text-bold">Payment:Cash Paid</small> -->
                    <p class="text-muted well well-sm shadow-none" style="margin-top: 10px;">
                        @if(readConfig('is_show_note_invoice')){{ readConfig('note_to_customer_invoice') }}@endif
                    </p>
                </div>
            </div>
            <div class="row">
                @if (($fbrIntegration ?? null) == 'enabled')

                    <div class="col-md-1 mt-2">
                        <!-- <h3>FBR Dig</h3> -->
                        <div id="qrcode"></div>
                        
                    </div>

                    <div class="col-md-1">
                        <div class="img">
                            @if($order->type == 'pos_invoice')
                            <img src="{{ asset('assets/images/fbr_pos_invoice_system.png') }}" style="width: 100%;" alt="">
                            @else
                            
                            <img src="{{ asset('assets/images/fbr_logo-icon.png') }}" style="width: 100%;" alt="">
                            @endif
                        </div>
                    </div>
                @else
                    <div class="col-md-2"></div>
                @endif
              <div class="col-md-8"></div>

              <div class="col-md-2">
                 <div class="img">
                      <img src="{{ asset('assets/images/imc.jpeg') }}" style="width: 100%;" alt="">
                  </div>
              </div>

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
window.addEventListener("load", window.print());

new QRCode(document.getElementById("qrcode"), {
    text: "{{ $order->fbr_invoice_no }}",
    width: 60,
    height: 60
});
</script>
@endpush