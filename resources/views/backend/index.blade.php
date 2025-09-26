@extends('backend.master')

@section('title', 'Dashboard')

<style>
.dashboard-card {
    background: #fff !important;              
    border-radius: 12px !important;
    color: #17a2b8 !important;                
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    transition: transform 0.2s ease-in-out;
    display: block;
    text-decoration: none;
}

.dashboard-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.25) !important;
}

.dashboard-card .inner h3 {
    font-size: 3.3rem !important;  /* Bigger numbers */
    font-weight: 700;
    margin: 0;
}


.dashboard-card-second {
    background: #fff !important;              
    border-radius: 12px !important;
    color: #28A745!important;                
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    transition: transform 0.2s ease-in-out;
    display: block;
    text-decoration: none;
}

.dashboard-card-second .inner h3 {
    font-size: 3.3rem !important;  /* Bigger numbers */
    font-weight: 700;
    margin: 0;
}


.dashboard-card-third {
    background: #fff !important;              
    border-radius: 12px !important;
    color: #FFC107!important;                
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    transition: transform 0.2s ease-in-out;
    display: block;
    text-decoration: none;
}

.dashboard-card-third .inner h3 {
    font-size: 3.3rem !important;  /* Bigger numbers */
    font-weight: 700;
    margin: 0;
}


.dashboard-card-forth {
    background: #fff !important;              
    border-radius: 12px !important;
    color: #DC3545!important;                
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    transition: transform 0.2s ease-in-out;
    display: block;
    text-decoration: none;
}

.dashboard-card-forth .inner h3 {
    font-size: 3.3rem !important;  /* Bigger numbers */
    font-weight: 700;
    margin: 0;
}

.ion-bag::before{
    color: #17a2b8 !important;
}

.ion-stats-bars::before{
    color: #28A745 !important;
}

.ion-person-add::before{
    color: #FFC107 !important;
}

.ion-pie-graph::before{
    color: #DC3545 !important;
}

.fa-users{
    color: white !important;
}

.info-box-number {
    font-size: 1.6rem; /* default se bara */
    font-weight: 600;
    /* color: #17a2b8 !important; */
}
</style>

@section('content')
<section class="content">
    {{-- @can('dashboard_view') --}}
@if (currentUserBusiness() && currentUserBusiness()->hasPermissionTo('dashboard_view'))
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box dashboard-card">
                    <span class="info-box-icon bg-info elevation-1"><i class="fas fa-cog"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Sale SubTotal</span>
                        <span class="info-box-number">
                            {{ currency()->symbol??'' }} {{ number_format($sub_total,2,'.',',') }}
                        </span>
                    </div>
                </div>
            </div>
            <!-- /.col -->
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box dashboard-card-forth">
                    <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-thumbs-up"></i></span>

                    <div class="info-box-content">
                        <span class="info-box-text">Sale Discount</span>
                        <span class="info-box-number">{{currency()->symbol??''}} {{number_format($discount,2,'.',',')}}</span>
                    </div>
                    <!-- /.info-box-content -->
                </div>
                <!-- /.info-box -->
            </div>
            <!-- /.col -->

            <!-- fix for small devices only -->
            <div class="clearfix hidden-md-up"></div>

            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box dashboard-card-second">
                    <span class="info-box-icon bg-success elevation-1"><i class="fas fa-shopping-cart"></i></span>

                    <div class="info-box-content">
                        <span class="info-box-text">Sale</span>
                        <span class="info-box-number">{{currency()->symbol??''}} {{number_format($total,2,'.',',')}}</span>
                    </div>
                    <!-- /.info-box-content -->
                </div>
                <!-- /.info-box -->
            </div>
            <!-- /.col -->
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box dashboard-card-third">
                    <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-users"></i></span>

                    <div class="info-box-content">
                        <span class="info-box-text">Sale Due </span>
                        <span class="info-box-number">{{currency()->symbol??''}} {{number_format($due,2,'.',',')}}</span>
                    </div>
                    <!-- /.info-box-content -->
                </div>
                <!-- /.info-box -->
            </div>
            <!-- /.col -->
        </div>

        <!-- Small boxes (Stat box) -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <a href="{{ route('backend.admin.customers.index') }}" class="small-box bg-info dashboard-card">
                    <div class="inner dashboard-card" >
                        <h3>{{ $total_customer }}</h3>
                        <p>Customers</p>
                    </div>
                    <div class="icon">
                        <i class="ion ion-bag"></i>
                    </div>
                </a>
            </div>


            <div class="col-lg-3 col-6">
                <a href="{{route('backend.admin.products.index')}}" class="small-box bg-info dashboard-card-second">
                    <div class="inner dashboard-card-second" >
                        <h3>{{ $total_product }}</h3>
                        <p>Products</p>
                    </div>
                    <div class="icon">
                        <i class="ion ion-stats-bars"></i>
                    </div>
                </a>
            </div>


            <div class="col-lg-3 col-6">
                <a href="{{route('backend.admin.orders.index')}}" class="small-box bg-info dashboard-card-third">
                    <div class="inner dashboard-card-third" >
                        <h3>{{ $total_order }}</h3>
                        <p>Sale</p>
                    </div>
                    <div class="icon">
                        <i class="ion ion-person-add"></i>
                    </div>
                </a>
            </div>


            <div class="col-lg-3 col-6">
                <a href="{{route('backend.admin.orders.index')}}" class="small-box bg-info dashboard-card-forth">
                    <div class="inner dashboard-card-forth" >
                        <h3>{{$total_sale_item}}</h3>
                        <p>Sale Item</p>
                    </div>
                    <div class="icon">
                        <i class="ion ion-pie-graph"></i>
                    </div>
                </a>
            </div>

            <!-- ./col -->
            {{-- <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>{{$total_sale_item}}</h3>
                        <p>Sale Item</p>
                    </div>
                    <div class="icon">
                        <i class="ion ion-pie-graph"></i>
                    </div>
                    <a href="{{route('backend.admin.orders.index')}}" class="small-box-footer">
                        More info
                        <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div> --}}
            <!-- ./col -->
        </div>
        <!-- /.row -->


        <div class="row">
            <div class="col-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Daily Total Sales <small>{{ $dateRange }}</small></h5>
                        <div class="input-group w-auto">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="far fa-calendar-alt"></i>
                                </span>
                            </div>
                            <input type="text" class="form-control" id="reservation" style="width: 180px;">
                        </div>
                    </div>

                    <div class="card-body">
                        <canvas id="dailySaleLineChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Monthly Total Sales <small>for {{ $currentYear }}</small></h5>
                    </div>
                    <div class="card-body">
                        <canvas id="barChartYear"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- @endcan --}}
    @endif
    <!-- /.container-fluid -->
</section>
@endsection
@push('script')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Wait for DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        const dailySaleChart = document.getElementById('dailySaleLineChart');
        const barChartYear = document.getElementById('barChartYear');

        // Debugging ke liye
        console.log('Dates data:', @json($dates));
        console.log('Amounts data:', @json($totalAmounts));
        console.log('Months data:', @json($months));
        console.log('Monthly amounts data:', @json($totalAmountMonth));

        // Daily Sales Line Chart
        if (dailySaleChart) {
            new Chart(dailySaleChart, {
                type: 'line',
                data: {
                    labels: @json($dates),
                    datasets: [{
                        label: 'Sales Amount',
                        data: @json($totalAmounts),
                        borderWidth: 2,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Daily Sales Trend'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Sales Amount'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    }
                }
            });
        }

        // Monthly Sales Bar Chart
        if (barChartYear) {
            new Chart(barChartYear, {
                type: 'bar',
                data: {
                    labels: @json($months),
                    datasets: [{
                        label: 'Sales Amount',
                        data: @json($totalAmountMonth),
                        borderWidth: 2,
                        backgroundColor: 'rgba(255, 99, 132, 0.8)',
                        borderColor: 'rgba(255, 99, 132, 1)'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Monthly Sales for {{ $currentYear }}'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Sales Amount'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Months'
                            }
                        }
                    }
                }
            });
        }
    });
</script>

<script>
    $(function() {
        //Date range picker with default range
        $('#reservation').daterangepicker({
            startDate: moment().subtract(29, 'days'),
            endDate: moment(),
            ranges: {
               'Today': [moment(), moment()],
               'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
               'Last 7 Days': [moment().subtract(6, 'days'), moment()],
               'Last 30 Days': [moment().subtract(29, 'days'), moment()],
               'This Month': [moment().startOf('month'), moment().endOf('month')],
               'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        });

        $('#reservation').on('apply.daterangepicker', function(ev, picker) {
            let selectedDateRange = picker.startDate.format('YYYY-MM-DD') + ' to ' + picker.endDate.format('YYYY-MM-DD');
            let url = new URL(window.location.href);
            url.searchParams.set('daterange', selectedDateRange);
            window.location.href = url.toString();
        });
    });
</script>
@endpush