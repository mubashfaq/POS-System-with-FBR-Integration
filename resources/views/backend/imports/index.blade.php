@extends('backend.master')

@section('title', 'Import Management')

@section('content')

    <style>
        .btn-expand {
            display: inline-flex;
            align-items: center;
            height: 31px;
            padding: 0 8px;
            border-radius: 6px;
            border: 1px solid transparent;
            font-size: 0.8rem;
            overflow: hidden;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .btn-expand i {
            flex-shrink: 0;
            width: 14px;
            text-align: center;
        }

        .btn-expand span {
            max-width: 0;
            opacity: 0;
            margin-left: 0;
            overflow: hidden;
            transition: max-width 0.25s ease, opacity 0.2s ease, margin-left 0.25s ease;
            white-space: nowrap;
        }

        .btn-expand:hover span {
            max-width: 160px;
            opacity: 1;
            margin-left: 6px;
        }

        .btn-expand.btn-outline-primary {
            border-color: #0d6efd;
            color: #0d6efd;
        }

        .btn-expand.btn-outline-primary:hover {
            background: #0d6efd;
            color: #fff;
        }

        .btn-expand.btn-outline-danger {
            border-color: #dc3545;
            color: #dc3545;
        }

        .btn-expand.btn-outline-danger:hover {
            background: #dc3545;
            color: #fff;
        }
    </style>

    {{-- ========== Top Row: New button ========== --}}
    <div class="mb-3 d-flex justify-content-end" style="margin-top: -54px">
        <a href="{{ route('backend.admin.imports.create') }}" class="btn btn-flat">
            <i class="fas fa-plus-circle"></i> New Import
        </a>
    </div>

    {{-- ========== Filter Row ========== --}}
    <div class="mb-3 d-flex justify-content-end align-items-center flex-wrap">

        {{-- Date Range --}}
        <div class="form-group mb-0">
            <input type="hidden" id="start_date">
            <input type="hidden" id="end_date">

            <button type="button" class="btn btn-default" id="daterange-btn">
                <i class="far fa-calendar-alt"></i>
                Filter by date
                <i class="fas fa-caret-down"></i>
            </button>
        </div>

    </div>

    {{-- ========== Table Card ========== --}}
    <div class="card">

        <div class="card-body p-2 p-md-4 pt-0">
            <div class="table-responsive p-0">
                <table id="datatables" class="table table-hover">
                    <thead>
                        <tr>
                            <th data-orderable="false">#</th>
                            <th>Import No</th>
                            <th>Container</th>
                            <th>Supplier</th>
                            <th>Godown</th>
                            <th>P Date</th>
                            <th>Clear Date</th>
                            <th>Currency</th>
                            <th>Inv Amount</th>
                            <th>Total CBF/CBM</th>
                            <th>Total Exp 1</th>
                            <th>Total Exp 2</th>
                            <th>Created</th>
                            <th data-orderable="false">Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

@endsection

@push('script')
    <script type="text/javascript">
        let table;

        $(function() {

            // Initialize hidden date fields
            $('#start_date').val('');
            $('#end_date').val('');

            // ---------- DataTable (ServerSide) ----------
            table = $('#datatables').DataTable({
                processing: true,
                serverSide: true,
                ordering: true,
                order: [
                    [12, 'desc']
                ], // created_at column — index shifted because we added Godown + Total CBF/CBM

                ajax: {
                    url: "{{ route('backend.admin.imports.index') }}",
                    data: function(d) {
                        d.start_date = $('#start_date').val();
                        d.end_date = $('#end_date').val();
                    }
                },

                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'import_no',
                        name: 'import_no'
                    },
                    {
                        data: 'container_code',
                        name: 'container_code'
                    },
                    {
                        data: 'supplier',
                        name: 'supplier',
                        orderable: false
                    },
                    {
                        data: 'godown',
                        name: 'godown',
                        orderable: false
                    },
                    {
                        data: 'p_date',
                        name: 'p_date'
                    },
                    {
                        data: 'clear_date',
                        name: 'clear_date'
                    },
                    {
                        data: 'currency',
                        name: 'currency',
                        className: 'text-center'
                    },
                    {
                        data: 'inv_amount',
                        name: 'inv_amount',
                        className: 'text-right'
                    },
                    {
                        data: 'total_cbf_cbm',
                        name: 'total_cbf_cbm',
                        className: 'text-right',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'total_expense_1',
                        name: 'total_expense_1',
                        className: 'text-right'
                    },
                    {
                        data: 'total_expense_2',
                        name: 'total_expense_2',
                        className: 'text-right'
                    },
                    {
                        data: 'created_at',
                        name: 'created_at'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            // ---------- Date Range Picker ----------
            let dateFilterSelected = false;

            $('#daterange-btn').daterangepicker({
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'),
                        moment().subtract(1, 'month').endOf('month')
                    ]
                },
                autoUpdateInput: false,
                locale: {
                    cancelLabel: 'Clear'
                }
            }, function(start, end) {
                dateFilterSelected = true;
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
                table.draw();
            });

            $('#daterange-btn').on('cancel.daterangepicker', function() {
                $('#start_date').val('');
                $('#end_date').val('');
                $(this).html(
                    '<i class="far fa-calendar-alt"></i> Filter by date <i class="fas fa-caret-down"></i>'
                );
                table.draw();
            });

        });
    </script>
@endpush
