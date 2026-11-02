@extends('backend.master')

@section('title', 'Sale Report')

@section('content')
    <div class="card">
        <div class="mt-n5 mb-3 d-flex justify-content-end align-items-center">
            <div class="mr-2 position-relative">
                <div class="input-group">
                    <input type="text" id="customer_search" class="form-control" placeholder="Search Customer..."
                        autocomplete="off">
                    @if (request('customer_id') && isset($customer))
                        <div class="input-group-append">
                            <a href="{{ route('backend.admin.sale.report', request()->except(['customer_id'])) }}"
                                class="btn btn-danger" title="Clear Filter">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    @endif
                </div>

                <div id="customer_search_results" class="list-group position-absolute w-100"
                    style="z-index: 9999; display: none; max-height: 300px; overflow-y: auto;"></div>
            </div>
            @if (request('customer_id') && isset($customer))
                <div class="mr-3">
                    <strong>Filtered by: {{ $customer->name }}</strong>
                </div>
            @endif
            <div class="form-group mb-0 mr-2">
                <select id="date_field_filter" class="form-control form-control-sm">
                    <option value="created_at" {{ $date_field === 'created_at' ? 'selected' : '' }}>
                        Order Created Date
                    </option>

                    <option value="order_date" {{ $date_field === 'order_date' ? 'selected' : '' }}>
                        Invoice Date
                    </option>
                </select>
            </div>

            @if ($isSuperAdmin)
                <div class="form-group mb-0 mr-2">
                    <select id="environment_filter" class="form-control form-control-sm">
                        <option value="" {{ empty($environment) ? 'selected' : '' }}>
                            All Environments
                        </option>

                        <option value="sandbox" {{ $environment === 'sandbox' ? 'selected' : '' }}>
                            Sandbox
                        </option>

                        <option value="production" {{ $environment === 'production' ? 'selected' : '' }}>
                            Production
                        </option>
                    </select>
                </div>
            @endif

            <div class="form-group mb-0">
                <div class="input-group">
                    <button type="button" class="btn btn-default float-right" id="daterange-btn">
                        <i class="far fa-calendar-alt"></i>
                        Filter by date
                        <i class="fas fa-caret-down"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-2 p-md-4 pt-0">
            <div class="row g-4">
                <div class="col-md-12">
                    <div class="card-body p-0">
                        <section class="invoice">
                            <!-- info row -->
                            <div class="row invoice-info">
                                <div class="col-sm-4">
                                </div>
                                <!-- /.col -->
                                <div class="col-sm-4">
                                    <address>
                                        <strong>Sale Report ({{ $start_date }} - {{ $end_date }})</strong><br>
                                    </address>
                                </div>
                                <!-- /.col -->
                                <div class="col-sm-2">
                                </div>
                                <!-- /.col -->
                            </div>
                            <!-- /.row -->
                            @php
                                $showFurtherTax = in_array($business->business_type, ['digital', 'both']);
                            @endphp
                            <!-- Table row -->
                            <div class="row justify-content-center">
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table id="datatables" class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th data-orderable="false">#</th>
                                                    <th>SaleId</th>
                                                    <th>Customer</th>
                                                    @if ($isSuperAdmin)
                                                        <th>Environment</th>
                                                    @endif
                                                    <th>Order Date</th>
                                                    <th>Created At</th>
                                                    <th>Item</th>
                                                    <th>Cost {{ currency()->symbol ?? '' }}</th>
                                                    <th>Sub Total {{ currency()->symbol ?? '' }}</th>
                                                    <th>Sale Tax {{ currency()->symbol ?? '' }}</th>
                                                    @if ($showFurtherTax)
                                                        <th>Further Tax {{ currency()->symbol ?? '' }}</th>
                                                    @endif
                                                    <th>Discount {{ currency()->symbol ?? '' }}</th>
                                                    <th>Total {{ currency()->symbol ?? '' }}</th>
                                                    <th>Paid {{ currency()->symbol ?? '' }}</th>
                                                    <th>Due {{ currency()->symbol ?? '' }}</th>
                                                    <th>Profit {{ currency()->symbol ?? '' }}</th>
                                                    <th>Loss {{ currency()->symbol ?? '' }}</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($orders as $index => $order)
                                                    <tr>
                                                        <td>{{ $index + 1 }}</td>
                                                        <td>#{{ $order->id }}</td>
                                                        <td>{{ $order->customer->name ?? '-' }}</td>
                                                        @if ($isSuperAdmin)
                                                            <td>
                                                                @if ($order->invoice_environment === 'sandbox')
                                                                    <span class="badge bg-primary">Sandbox</span>
                                                                @elseif($order->invoice_environment === 'production')
                                                                    <span class="badge bg-danger">Production</span>
                                                                @else
                                                                    -
                                                                @endif
                                                            </td>
                                                        @endif
                                                        <td class="text-nowrap date-col">
                                                            {{ $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d-m-Y') : '-' }}
                                                        </td>
                                                        <td class="text-nowrap date-col">
                                                            {{ $order->created_at ? $order->created_at->format('d-m-Y') : '-' }}
                                                        </td>
                                                        <td>{{ $order->total_item }}</td>
                                                        <td>{{ number_format($order->order_cost, 2, '.', ',') }}</td>
                                                        <td>{{ number_format($order->sub_total, 2, '.', ',') }}</td>
                                                        <td>
                                                            @if (!is_null($order->total_sale_tax))
                                                                {{ number_format($order->total_sale_tax, 2, '.', ',') }}
                                                            @else
                                                                {{ number_format($order->order_sales_tax, 2, '.', ',') }}
                                                            @endif
                                                        </td>
                                                        @if ($showFurtherTax)
                                                            <td>
                                                                @if (!is_null($order->total_further_tax))
                                                                    {{ number_format($order->total_further_tax, 2, '.', ',') }}
                                                                @else
                                                                    {{ number_format($order->order_further_tax, 2, '.', ',') }}
                                                                @endif
                                                            </td>
                                                        @endif
                                                        <td>{{ number_format($order->discount, 2, '.', ',') }}</td>
                                                        <td>{{ number_format($order->total, 2, '.', ',') }}</td>
                                                        <td>{{ number_format($order->paid, 2, '.', ',') }}</td>
                                                        <td>{{ number_format($order->due, 2, '.', ',') }}</td>
                                                        <td>{{ number_format($order->order_profit, 2, '.', ',') }}</td>
                                                        <td>{{ number_format($order->order_loss, 2, '.', ',') }}</td>
                                                        <td>
                                                            @if ($order->status)
                                                                Paid
                                                            @else
                                                                Due
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="7" class="text-center">No sells found.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                            <tfoot>
                                                <tr class="report-summary-row">
                                                    <td colspan="15" class="text-end">
                                                        <span class="mr-3">
                                                            <i class="fas fa-chart-line text-success"></i>
                                                            Total Profit:
                                                            <strong>{{ number_format($profit, 2, '.', ',') }}</strong>
                                                        </span>

                                                        <span class="mr-3">
                                                            <i class="fas fa-chart-line text-danger"></i>
                                                            Total Loss:
                                                            <strong>{{ number_format($loss, 2, '.', ',') }}</strong>
                                                        </span>

                                                        <span class="mr-3">
                                                            <i class="fas fa-percentage text-warning"></i>
                                                            Total Sale Tax:
                                                            <strong>{{ number_format($sales_tax, 2, '.', ',') }}</strong>
                                                        </span>

                                                        @if ($showFurtherTax)
                                                            <span class="mr-3">
                                                                <i class="fas fa-file-invoice-dollar text-info"></i>
                                                                Total Further Tax:
                                                                <strong>{{ number_format($further_tax, 2, '.', ',') }}</strong>
                                                            </span>
                                                        @endif

                                                        <span class="mr-3">
                                                            <i class="fas fa-money-bill-wave text-primary"></i>
                                                            Total:
                                                            <strong
                                                                class="text-primary">{{ number_format($total, 2, '.', ',') }}</strong>
                                                        </span>
                                                    </td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                                <!-- /.col -->
                            </div>
                            <!-- /.row -->
                            <div class="row no-print">
                                <div class="col-12">
                                    <button type="button" onclick="window.print()" class="btn btn-success float-right"><i
                                            class="fas fa-print"></i> Print</a>
                                    </button>
                                </div>
                            </div>
                            <!-- /.row -->
                        </section>
                    </div>
                </div>
            </div>
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
    <script>
        $(function() {
            // Extract start and end dates from URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const startDate = urlParams.get('start_date') || moment().subtract(29, 'days').format(
                'YYYY-MM-DD'); // Default to last 30 days if not present
            const endDate = urlParams.get('end_date') || moment().format(
                'YYYY-MM-DD'); // Default to today if not present

            // Initialize the date range picker
            $('#daterange-btn').daterangepicker({
                    ranges: {
                        'Today': [moment(), moment()],
                        'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                        'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                        'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                        'This Month': [moment().startOf('month'), moment().endOf('month')],
                        'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1,
                            'month').endOf('month')]
                    },
                    startDate: moment(startDate, "YYYY-MM-DD"),
                    endDate: moment(endDate, "YYYY-MM-DD")
                },
                function(start, end) {
                    // Update the button text with the selected range
                    $('#daterange-btn span').html(start.format('MMMM D, YYYY') + ' - ' + end.format(
                        'MMMM D, YYYY'));

                    // Redirect with selected start and end dates
                    const dateField = $('#date_field_filter').val();
                    const environment = $('#environment_filter').val();

                    let url = '{{ route('backend.admin.sale.report') }}' +
                        '?start_date=' + start.format('YYYY-MM-DD') +
                        '&end_date=' + end.format('YYYY-MM-DD') +
                        '&date_field=' + encodeURIComponent(dateField);

                    @if ($isSuperAdmin)
                        if (environment) {
                            url += '&environment=' + encodeURIComponent(environment);
                        }
                    @endif

                    @if (request('customer_id'))
                        url += '&customer_id={{ request('customer_id') }}';
                    @endif

                    window.location.href = url;
                }
            );

            // Set the initial display text for the date range button
            $('#daterange-btn span').html(moment(startDate, "YYYY-MM-DD").format('MMMM D, YYYY') + ' - ' + moment(
                endDate, "YYYY-MM-DD").format('MMMM D, YYYY'));
            // Customer Search Logic
            let searchTimeout;
            $('#customer_search').on('keyup', function() {
                const query = $(this).val();
                clearTimeout(searchTimeout);

                if (query.length < 2) {
                    $('#customer_search_results').hide();
                    return;
                }

                searchTimeout = setTimeout(function() {
                    $.ajax({
                        url: "{{ route('backend.admin.customers.search') }}",
                        type: "GET",
                        data: {
                            term: query
                        },
                        success: function(data) {
                            let html = '';
                            if (data.length > 0) {
                                data.forEach(function(customer) {
                                    html += `<a href="#" class="list-group-item list-group-item-action customer-item" data-id="${customer.id}">
                                        <div class="d-flex w-100 justify-content-between">
                                          <h6 class="mb-1">${customer.name}</h6>
                                          <small>${customer.phone}</small>
                                        </div>
                                        <p class="mb-1 text-muted small">${customer.address || 'No Address'}</p>
                                    </a>`;
                                });
                                $('#customer_search_results').html(html).show();
                            } else {
                                $('#customer_search_results').html(
                                    '<div class="list-group-item">No customers found</div>'
                                ).show();
                            }
                        }
                    });
                }, 300);
            });

            // Handle click on customer result
            $(document).on('click', '.customer-item', function(e) {
                e.preventDefault();
                const customerId = $(this).data('id');

                // Get current URL parameters
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('customer_id', customerId);

                // Redirect to new URL
                window.location.href = window.location.pathname + '?' + urlParams.toString();
            });

            // Hide search results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#customer_search, #customer_search_results').length) {
                    $('#customer_search_results').hide();
                }
            });

            $('#date_field_filter').on('change', function() {
                applySaleReportFilters();
            });

            $('#environment_filter').on('change', function() {
                applySaleReportFilters();
            });

            function applySaleReportFilters() {
                const urlParams = new URLSearchParams(window.location.search);

                urlParams.set('date_field', $('#date_field_filter').val());

                @if ($isSuperAdmin)
                    const environment = $('#environment_filter').val();

                    if (environment) {
                        urlParams.set('environment', environment);
                    } else {
                        urlParams.delete('environment');
                    }
                @endif

                window.location.href =
                    '{{ route('backend.admin.sale.report') }}?' +
                    urlParams.toString();
            }
        });
    </script>
@endpush
