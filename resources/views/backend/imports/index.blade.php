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
                            <th>Total Expense</th>
                            <th>Created</th>
                            <th data-orderable="false">Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>


    <x-backend.modal id="importDetailsModal" title="Import Details" icon="fas fa-boxes" size="xl">

        <div id="importDetailsLoading" class="text-center py-5">
            <i class="fas fa-spinner fa-spin fa-2x mb-3 import-loading-icon"></i>
            <div>Loading import details...</div>
        </div>

        <div id="importDetailsContent" style="display:none;">
            <div class="modal-section">
                <div class="text-muted small mb-1">IMPORT NUMBER</div>
                <div class="fw-bold" style="font-size:1.05rem;">
                    <i class="fas fa-file-invoice me-1 text-brand"></i>
                    <span id="detailsImportNo"></span>
                </div>
            </div>

            <div class="modal-section">
                <h6 class="modal-section-title"><i class="fas fa-cubes"></i> Product Details</h6>
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
                                <th>Cost / Pc</th>
                                <th>Sale Price</th>
                                <th>Sale Percent</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="detailsProductRows"></tbody>
                        <tfoot>
                            <tr>
                                <th colspan="11" class="text-end">Total</th>
                                <th id="detailsProductsTotal" class="text-end">0.00</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="modal-section">
                <h6 class="modal-section-title"><i class="fas fa-money-bill-wave"></i> Category Expense Details</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Category</th>
                                <th class="text-end">CBF / CBM</th>
                                <th class="text-end">Category Expense</th>
                            </tr>
                        </thead>
                        <tbody id="detailsExpenseRows"></tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total Category Expenses</th>
                                <th id="detailsExpensesTotal" class="text-end">0.00</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="modal-section">
                <h6 class="modal-section-title"><i class="fas fa-truck"></i> Expense Details</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Expense</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="detailsFixedExpenseRows"></tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2" class="text-end">Total Fixed Expenses</th>
                                <th id="detailsFixedExpensesTotal" class="text-end">0.00</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div id="importDetailsError" class="alert alert-danger" style="display:none;">
            <i class="fas fa-exclamation-circle me-1"></i>
            Unable to load import details. Please try again.
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-1"></i> Close
            </button>
        </x-slot:footer>
    </x-backend.modal>

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
                    [10, 'desc']
                ],

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
                        data: 'total_expense',
                        name: 'total_expense',
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
                $('#detailsFixedExpenseRows').empty();
                $('#detailsFixedExpensesTotal').text('0.00');

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

                                const breakdown = product.cost_breakdown || {};
                                const costTip = [
                                    'Price: ' + money(breakdown.price_pc || 0),
                                    'Category expense: ' + money(breakdown
                                        .category_per_pc || 0) +
                                    ' (' + money(breakdown.category_total || 0) +
                                    ' ÷ ' + (breakdown.category_pcs || 0) + ' pcs)',
                                    'Total expense: ' + money(breakdown
                                        .global_per_pc || 0) +
                                    ' (' + money(breakdown.global_total || 0) +
                                    ' ÷ ' + (breakdown.total_pcs || 0) + ' pcs)',
                                    'Cost: ' + money(product.cost_pc || 0),
                                ].join('\n');

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
                                        <td class="text-right" title="${escapeHtml(costTip)}" style="cursor:help;">
                                            ${money(product.cost_pc)}
                                        </td>
                                        <td class="text-right">${money(product.sale_price)}</td>
                                        <td class="text-right">${escapeHtml(product.sale_percent)}%</td>
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

                        // -------- Fixed expense breakdown --------
                        let fixedHtml = '';
                        let fixedTotal = 0;

                        if (data.fixed_expenses && data.fixed_expenses.length) {
                            data.fixed_expenses.forEach(function(row, index) {
                                const amount = Number(row.amount) || 0;
                                fixedTotal += amount;

                                fixedHtml += `
                                    <tr>
                                        <td>${index + 1}</td>
                                        <td>${escapeHtml(row.label)}</td>
                                        <td class="text-right">${money(amount)}</td>
                                    </tr>
                                `;
                            });
                        } else {
                            fixedHtml = `
                                <tr>
                                    <td colspan="3" class="text-center text-muted">
                                        No fixed expenses found.
                                    </td>
                                </tr>
                            `;
                        }

                        $('#detailsFixedExpenseRows').html(fixedHtml);
                        $('#detailsFixedExpensesTotal').text(money(fixedTotal));

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
