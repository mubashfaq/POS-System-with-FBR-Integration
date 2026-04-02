@extends('backend.master')
@section('title', 'Receipt_'.$order->id)
@section('content')

<div class="card">
  <!-- Main content -->
  <div class="receipt-container mt-0" id="printable-section" style="max-width: {{ $maxWidth}}; font-size: 12px; font-family: 'Courier New', Courier, monospace;">
    <div class="text-center">
 @if(!empty($invoiceSettings->logo) && $invoiceSettings->logo == 1 && !empty($business->logo))
    <img src="{{ asset('storage/'.$business->logo) }}" height="100" width="100" alt="Logo"><br>
  @endif

  @if(!empty($invoiceSettings->site_name) && $invoiceSettings->site_name == 1)
    <h6 align="center" class="mt-1">{{ $business->business_name ?? '' }}</h6> 
  @endif

  @if(!empty($invoiceSettings->address) && $invoiceSettings->address == 1)
   <div align="center" class="mt-1"> {{ $business->address ?? '' }} </div> 
  @endif

  <div class="mt-1">NTN: {{ $business->ntn ?? 'NTN' }}</div>

  <div class="mt-1">
          <p>{{ date('d-M-Y') }} {{ date('h:i:s A') }}</p>
  </div>

  <!-- @if(!empty($invoiceSettings->phone) && $invoiceSettings->phone == 1)
    {{ $business->phone ?? '' }}<br>
  @endif -->

   
    
  

  @if(!empty($invoiceSettings->email) && $invoiceSettings->email == 1)
    {{ $business->email ?? '' }}<br>
  @endif
    </div>
    {{ 'User: '.auth()->user()->name}}<br>
    <!-- {{ 'Order: #'.$order->id}}<br> -->
    <!-- <hr> -->
      <!-- <div>Mode of Payment: {{ $order->payment_mode }}</div> -->

    <div class="row justify-content-between mx-auto">
     
      <div class="text-left">
        @if(readConfig('is_show_customer_invoice'))
        <address>
          Customer : {{ $order->customer->name ?? 'N/A' }}<br>
          <!-- Address: {{ $order->customer->address ?? 'N/A' }}<br> -->
          <!-- Phone: {{ $order->customer->phone ?? 'N/A' }}<br> -->
         
        </address>
        @endif
      </div>
<!-- 
      <div class="text-right">
        <address class="text-right">
          <p>{{ date('d-M-Y') }}</p>
          <p>{{ date('h:i:s A') }}</p>
        </address>
      </div> -->
    </div>
    <hr>
    <table style="width: 100%;">
      <thead>
        <tr>
          <th style="text-align: left;">Product</th>
          <th style="text-align: right;"></th>
          <!-- <th style="text-align: right;">Qty</th> -->
          <!-- <th style="text-align: right;">Price {{ currency()->symbol}}</th> -->
          <th style="text-align: right;">Total </th>
        </tr>
      </thead>
      


      <tbody>
   @php $totalTaxAmount = 0; @endphp
@foreach ($order->products as $item)
@php
    // Ensure numeric values
    $unitPrice = (float) $item->discounted_price;
    $quantity  = (float) $item->quantity;
    $total     = (float) $item->total;

    // Har product ka apna tax rate
    $taxRateRaw = $item->product->pos_sale_tax ?? '0'; // fallback if null
    $taxRateNumeric = (float) str_replace('%', '', $taxRateRaw);

    // Is product ka tax amount
    $currentProductTax = ($unitPrice * $quantity * $taxRateNumeric) / 100;

    // Poore order ka total tax jama karna
    $totalTaxAmount += $currentProductTax;

    $pfinalPricePerUnit = $unitPrice - ($unitPrice * $taxRateNumeric / 100);
    $pfinalLineTotal = $total - $currentProductTax;
@endphp
<tr>
    <td>{{ $item->product->name }}</td>
    <td class="text-center">{{ $quantity }} * {{ number_format($pfinalPricePerUnit, 2) }}</td>
    <td class="text-right">{{ number_format($pfinalLineTotal, 2) }}</td>
</tr>
@endforeach
</tbody>
</table>

<hr>

<div class="summary">
    <table style="width: 100%;">
        <tr>
            @php $subTotalMinusTax = $order->sub_total - $totalTaxAmount; @endphp
            <td class="text-right"> Subtotal (Excl. Tax): {{ number_format($subTotalMinusTax, 2) }}</td>
        </tr>
        <tr>
            <td class="text-right"> Sales Tax: {{ number_format($totalTaxAmount, 2) }}</td>
        </tr>
        <tr>
            <td class="text-right"> Discount: {{ number_format($order->discount, 2) }}</td>
        </tr>
        <tr>
            <td class="text-right"> PoS Fee: 1.00</td>
        </tr>
        <tr>
            {{-- Total calculation: Subtotal - Discount + POS Fee --}}
            <td class="text-right"> Total: <strong>{{ number_format($order->total + 1, 2) }}</strong></td>
        </tr>
         <tr>
          <!-- <td>Due:</td> -->
          <td class="text-right"> Due: {{number_format($order->due, 2) }}</td>
        </tr>
    </table>

    </div>
    <hr>
    <div class="text-center">
      <h6>FBR invoice #</h6>
      <h6>{{ $order->fbr_invoice_no }}</h6>
      <!-- Qr and image -->
      <div class="row justify-content-center">
        <div class="col-6 col-md-3 mt-2">
            <!-- <h3>FBR Dig</h3> -->
            <div id="qrcode"></div>
              
        </div>

        <style>
          
        </style>

        <div class="col-6 col-md-2 mr-md-4 fbr-logo">
          <div class="img ">
            @if($order->type == 'pos_invoice')
              <img src="{{ asset('assets/images/fbr_pos_invoice_system.png') }}" alt="">
            @else
              <img src="{{ asset('assets/images/fbr_logo-icon.png') }}" alt="">
            @endif
          </div>
        </div>
      </div>

    <p class="mt-2">Verify this invoice through FBR TaxAsaan MobileApp or SMS at 9966 and win exciting prizes in draw</p>

    <p>POS software by techuick</p>
    @if(!empty($invoiceSettings->note_to_customer) && $invoiceSettings->note_to_customer == 1)
      <p class="text-muted" style="font-size: 12px;">{{ $invoiceSettings->note_msg ?? '' }}</p>
    @endif
    </div>
  </div>

  <!-- Print Button -->
  <div class="text-center mt-3 no-print pb-3">
    <button type="button" onclick="window.print()" class="btn bg-gradient-primary text-white"><i class="fas fa-print"></i> Print</button>
  </div>
</div>
@endsection

@push('style')
<style>
  .fbr-logo img {
      width: 200%;
    }

    @media screen and (max-width: 767px) {
      .fbr-logo img {
        width: 100%;
        max-width: 80px;
      }

      .fbr-logo {
        margin-top: -8px;
      }
    }
  .receipt-container {
    border: 1px dotted #000;
    padding: 8px;
  }

  hr {
    border: none;
    border-top: 1px dashed #000;
    margin: 5px 0;
  }

  table {
    width: 100%;
  }

  td,
  th {
    padding: 2px 0;
  }

  .text-right {
    text-align: right;
  }

  @media print {
    @page {
      margin-top: 5px !important;
      margin-left: 0px !important;
      padding-left: 0px !important;
    }

    footer {
      display: none !important;
    }
  }
</style>
@endpush

@push('script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
  new QRCode(document.getElementById("qrcode"), {
    text: "{{ $order->fbr_invoice_no }}",
    width: 60,
    height: 60
});

  window.print();
</script>
@endpush