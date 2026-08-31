@extends('backend.master')
@section('title', 'REC-' . $order->business_id . '-' . str_pad($order->invoice_number, 4, '0', STR_PAD_LEFT))
@section('content')

  <div class="card">
    <!-- Main content -->
    <div class="receipt-container mt-0" id="printable-section"
      style="max-width: {{ $maxWidth}}; font-size: 12px; font-family: 'Courier New', Courier, monospace;">
      <div class="text-center">
        @if(!empty($invoiceSettings->logo) && $invoiceSettings->logo == 1)
          @if ( !empty($business->logo))
            <img src="{{ asset('storage/' . $business->logo) }}" height="30" width="70" alt="Logo"><br>
          @else
            <img src="{{ asset('storage/logos/default-business.png') }}" height="50" width="70" alt="Logo"><br>
          @endif 
        @endif

        @if(!empty($invoiceSettings->site_name) && $invoiceSettings->site_name == 1)
          <h3>{{ $business->business_name ?? '' }}</h3> <br>
        @endif

        @if(!empty($invoiceSettings->address) && $invoiceSettings->address == 1)
          {{ $business->address ?? '' }}<br>
        @endif

        @if(!empty($invoiceSettings->phone) && $invoiceSettings->phone == 1)
          {{ $business->phone ?? '' }}<br>
        @endif

        @if(!empty($invoiceSettings->email) && $invoiceSettings->email == 1)
          {{ $business->email ?? '' }}<br>
        @endif

        <div class="mt-1">
          <p>{{ date('d-M-Y') }} {{ date('h:i:s A') }}</p>
        </div>
      </div>
      {{ 'User: ' . auth()->user()->name}}<br>
      {{ 'Order: #' . $order->id}}<br>
      <hr>
      <div class="row justify-content-between mx-auto">
        <div class="text-left">
          @if(readConfig('is_show_customer_invoice'))
            <address>
              Name: {{ $order->customer->name ?? 'N/A' }}<br>
              Address: {{ $order->customer->address ?? 'N/A' }}<br>
              Phone: {{ $order->customer->phone ?? 'N/A' }}<br>
              Sale Date: {{date('d/m/Y', strtotime($order->order_date ?? $order->created_at))}}
            </address>
          @endif
        </div>
        {{-- <div class="text-right">
          <address class="text-right">
            <p>{{ date('d-M-Y') }}</p>
            <p>{{ date('h:i:s A') }}</p>
          </address>
        </div> --}}
      </div>
      <hr>
      <table style="width: 100%;">
        <thead>
          <tr>
            <th style="text-align: left;">Product</th>
            <th style="text-align: right;"></th>
            <!-- <th style="text-align: right;">Qty</th> -->
            <!-- <th style="text-align: right;">Price {{ currency()->symbol}}</th> -->
            <th style="text-align: right;">Total {{ currency()->symbol}}</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($order->products->where('is_accessory', 0) as $item)
            <tr>
              <td>{{ $item->product->name }}</td>
              <!-- <td class="text-right">{{ $item->quantity }}</td> -->
              <td class="text-right">{{ $item->quantity }}*{{ $item->discounted_price}}</td>
              <td class="text-right">{{ number_format($item->sub_total, 2)}}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <hr>
      <div class="summary">
        <table style="width: 100%;">
          <tr>
            <td>Subtotal (Incl. Tax):</td>
            <td class="text-right">{{number_format($order->sub_total, 2) }}</td>
          </tr>
          <tr>
            <td>Discount:</td>
            <td class="text-right">{{number_format($order->discount, 2) }}</td>
          </tr>
          <tr>
            <td><strong>Total:</strong></td>
            <td class="text-right"><strong>{{number_format($order->total, 2) }}</strong></td>
          </tr>
          <tr>
            <td>Paid:</td>
            <td class="text-right">{{number_format($order->paid, 2) }}</td>
          </tr>
          <tr>
            <td>Due:</td>
            <td class="text-right">{{number_format($order->due, 2) }}</td>
          </tr>
        </table>
      </div>
      <hr>
      <div class="text-center">
        @if (($fbrIntegration ?? null) == 'enabled')
          @if ($order->order_stage !== 'draft')
            
            <h6>FBR invoice #</h6>
            <h6>{{ $order->fbr_invoice_no }}</h6>
            <!-- Qr and image -->
            <div class="row justify-content-center">
              <div class="col-6 col-md-3 mt-2">
                <!-- <h3>FBR Dig</h3> -->
                <div id="qrcode"></div>

              </div>

              <div class="col-6 col-md-2 mr-md-4 fbr-logo">
                <div class="img">
                  @if($order->type == 'pos_invoice')
                    <img src="{{ asset('assets/images/fbr_pos_invoice_system.png') }}" alt="POS Invoice">
                  @else
                    <img src="{{ asset('assets/images/fbr_logo-icon.png') }}" alt="Digital Invoice">
                  @endif
                </div>
              </div>
            </div>

            <p class="mt-2">Verify this invoice through FBR TaxAsaan MobileApp or SMS at 9966 and win exciting prizes in draw
            </p>
          @endif
        @else
          <p class="mt-2">FBR is not integrated.</p>
        @endif
        <p>POS software by techuick</p>
        @if(!empty($invoiceSettings->note_to_customer) && $invoiceSettings->note_to_customer == 1)
          <p class="text-muted" style="font-size: 12px;">{{ $invoiceSettings->note_msg ?? '' }}</p>
        @endif
      </div>
    </div>

    <!-- Print Button -->
    <div class="text-center mt-3 no-print pb-3">
      <button type="button" onclick="window.print()" class="btn bg-gradient-primary text-white"><i
          class="fas fa-print"></i> Print</button>
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