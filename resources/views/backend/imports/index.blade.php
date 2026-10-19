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


        .btn-expand.btn-outline-success {
            border-color: #198754;
            color: #198754;
        }

        .btn-expand.btn-outline-success:hover {
            background: #198754;
            color: #fff;
        }

        /* Import Details Modal */
        #importDetailsModal .modal-content {
            border: 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.18);
        }

        #importDetailsModal .modal-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            border-left: 5px solid #fd6802;
            padding: 18px 22px;
        }

        #importDetailsModal .modal-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            color: #343a40;
        }

        #importDetailsModal .modal-title i {
            color: #fd6802;
        }

        #importDetailsModal .close {
            font-size: 1.7rem;
            opacity: 0.65;
            transition: color 0.2s, opacity 0.2s;
        }

        #importDetailsModal .close:hover {
            color: #fd6802;
            opacity: 1;
        }

        #importDetailsModal .modal-body {
            background: #f8f9fb;
            padding: 22px;
        }

        #importDetailsModal .import-detail-summary {
            background: #fff;
            border: 1px solid #eee;
            border-left: 4px solid #fd6802;
            border-radius: 8px;
            padding: 13px 16px;
            margin-bottom: 24px;
        }

        #importDetailsModal .import-detail-section {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 22px;
        }

        #importDetailsModal .import-section-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #343a40;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #importDetailsModal .import-section-title i {
            color: #fd6802;
        }

        #importDetailsModal .table {
            margin-bottom: 0;
            font-size: 0.85rem;
            vertical-align: middle;
        }

        #importDetailsModal .table thead th {
            background: #fff4ec;
            color: #7a421f;
            font-weight: 700;
            border-top: 0;
            border-bottom: 1px solid #f0d9c9;
            border-right: 1px solid #f4e7df;
            white-space: nowrap;
            padding: 11px 10px;
        }

        #importDetailsModal .table tbody td,
        #importDetailsModal .table tfoot th {
            padding: 10px;
            vertical-align: middle;
        }

        #importDetailsModal .table tfoot {
            background: #fff7f0;
        }

        #importDetailsModal .table tfoot th:last-child {
            color: #d95700;
            white-space: nowrap;
        }

        #importDetailsModal .modal-footer {
            background: #fff;
            border-top: 1px solid #eee;
            padding: 14px 22px;
        }

        #importDetailsModal .import-modal-close {
            border-radius: 6px;
            padding: 7px 18px;
        }

        #importDetailsModal #importDetailsLoading {
            color: #6c757d;
        }

        #importDetailsModal .import-loading-icon {
            color: #fd6802;
        }

        @media (max-width: 767px) {
            #importDetailsModal .modal-body {
                padding: 12px;
            }

            #importDetailsModal .import-detail-section {
                padding: 10px;
            }
        }
    </style>

    {{-- ========== Top Row: New button ========== --}}
    <div class="mb-3 d-flex justify-content-end" style="margin-top: -54px">
        <div class="form-group mb-0 mx-2">
            <input type="hidden" id="start_date">
            <input type="hidden" id="end_date">

            <button type="button" class="btn btn-default" id="daterange-btn">
                <i class="far fa-calendar-alt"></i>
                Filter by date
                <i class="fas fa-caret-down"></i>
            </button>
        </div>

        <a href="{{ route('backend.admin.imports.create') }}" class="btn btn-flat">
            <i class="fas fa-plus-circle"></i> New Import
        </a>

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


    <div class="modal fade" id="importDetailsModal" tabindex="-1" role="dialog" aria-labelledby="importDetailsTitle"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="importDetailsTitle">
                        <i class="fas fa-boxes"></i>
                        Import Details
                    </h5>

                    <button type="button" class="close close-import-details" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">

                    <div id="importDetailsLoading" class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-2x import-loading-icon mb-3"></i>
                        <div>Loading import details...</div>
                    </div>

                    <div id="importDetailsContent" style="display:none;">

                        <div class="import-detail-summary">
                            <div class="text-muted small mb-1">IMPORT NUMBER</div>
                            <div class="font-weight-bold" style="font-size: 1.05rem;">
                                <i class="fas fa-file-invoice mr-1" style="color:#fd6802;"></i>
                                <span id="detailsImportNo"></span>
                            </div>
                        </div>

                        <div class="import-detail-section">
                            <h6 class="import-section-title">
                                <i class="fas fa-cubes"></i>
                                Product Details
                            </h6>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Code</th>
                                            <th>Product</th>
                                            <th>Packing</th>
                                            <th>Pcs / Ctn</th>
                                            <th>Ctn Qty</th>
                                            <th>Total Pcs</th>
                                            <th>Price / Pc</th>
                                            <th>Price / Dz</th>
                                            <th class="text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detailsProductRows"></tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="9" class="text-right">Total</th>
                                            <th id="detailsProductsTotal" class="text-right">0.00</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="import-detail-section mb-0">
                            <h6 class="import-section-title">
                                <i class="fas fa-money-bill-wave"></i>
                                Category Expense Details
                            </h6>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Category</th>
                                            <th class="text-right">CBF / CBM</th>
                                            <th class="text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detailsExpenseRows"></tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="3" class="text-right">
                                                Total Category Expenses
                                            </th>
                                            <th id="detailsExpensesTotal" class="text-right">0.00</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                    </div>

                    <div id="importDetailsError" class="alert alert-danger" style="display:none;">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        Unable to load import details. Please try again.
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary import-modal-close close-import-details">
                        <i class="fas fa-times mr-1"></i> Close
                    </button>
                </div>

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


            function escapeHtml(value) {
                return $('<div>').text(value == null ? '-' : String(value)).html();
            }

            function money(value) {
                return (Number(value) || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            $('#datatables').on('click', '.view-import-details', function() {
                const url = $(this).data('url');

                $('#detailsImportNo').text('');
                $('#detailsProductRows').empty();
                $('#detailsExpenseRows').empty();
                $('#detailsProductsTotal').text('0.00');
                $('#detailsExpensesTotal').text('0.00');

                $('#importDetailsContent').hide();
                $('#importDetailsError').hide();
                $('#importDetailsLoading').show();

                $('#importDetailsModal').modal('show');

                $.ajax({
                    url: url,
                    type: 'GET',
                    dataType: 'json',

                    success: function(data) {
                        $('#detailsImportNo').text(data.import_no || '-');

                        let productHtml = '';
                        let productTotal = 0;

                        if (data.products && data.products.length) {
                            data.products.forEach(function(product, index) {
                                const amount = Number(product.amount) || 0;
                                productTotal += amount;

                                productHtml += `
                                    <tr>
                                        <td>${index + 1}</td>
                                        <td>${escapeHtml(product.sku)}</td>
                                        <td>${escapeHtml(product.name)}</td>
                                        <td>${escapeHtml(product.unit)}</td>
                                        <td class="text-right">${escapeHtml(product.pcs_per_carton)}</td>
                                        <td class="text-right">${escapeHtml(product.ctn_qty)}</td>
                                        <td class="text-right">${escapeHtml(product.total_pcs)}</td>
                                        <td class="text-right">${money(product.price_pc)}</td>
                                        <td class="text-right">${money(product.price_dz)}</td>
                                        <td class="text-right">${money(amount)}</td>
                                    </tr>
                                `;
                            });
                        } else {
                            productHtml = `
                                <tr>
                                    <td colspan="10" class="text-center text-muted">
                                        No products found.
                                    </td>
                                </tr>
                            `;
                        }

                        $('#detailsProductRows').html(productHtml);
                        $('#detailsProductsTotal').text(money(productTotal));

                        let expenseHtml = '';
                        let expenseTotal = 0;

                        if (data.expenses && data.expenses.length) {
                            data.expenses.forEach(function(expense, index) {
                                const amount = Number(expense.amount) || 0;
                                expenseTotal += amount;

                                expenseHtml += `
                                    <tr>
                                        <td>${index + 1}</td>
                                        <td>${escapeHtml(expense.category)}</td>
                                        <td class="text-right">${money(expense.cbf_cbm)}</td>
                                        <td class="text-right">${money(amount)}</td>
                                    </tr>
                                `;
                            });
                        } else {
                            expenseHtml = `
                                <tr>
                                    <td colspan="4" class="text-center text-muted">
                                        No category expenses found.
                                    </td>
                                </tr>
                            `;
                        }

                        $('#detailsExpenseRows').html(expenseHtml);
                        $('#detailsExpensesTotal').text(money(expenseTotal));

                        $('#importDetailsLoading').hide();
                        $('#importDetailsContent').show();
                    },

                    error: function() {
                        $('#importDetailsLoading').hide();
                        $('#importDetailsError').show();
                    }
                });
            });


            $(document).on('click', '.close-import-details', function() {
                $('#importDetailsModal').modal('hide');
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
