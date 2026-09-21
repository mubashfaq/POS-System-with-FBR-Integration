@extends('backend.master')

@section('title', 'FBR Invoice Reconciliation')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">

                <div class="mb-4">
                    <p class="text-muted mb-0">
                        Upload the FBR sales ledger export and check which system invoices are missing from it.
                    </p>
                </div>

                <form id="reconcileForm" enctype="multipart/form-data">
                    @csrf

                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="from_date" class="form-label">From Date</label>
                            <input type="date" name="from_date" id="from_date" class="form-control" required>
                        </div>

                        <div class="col-md-3">
                            <label for="to_date" class="form-label">To Date</label>
                            <input type="date" name="to_date" id="to_date" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label for="ledger_file" class="form-label">
                                FBR Sales Ledger (.xls / .xlsx / .csv)
                            </label>
                            <input type="file" name="ledger_file" id="ledger_file" class="form-control"
                                accept=".xls,.xlsx,.csv" required>
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn action-button w-100" id="submitBtn">
                                Check
                            </button>
                        </div>
                    </div>
                </form>

                <div id="resultArea" class="mt-4" style="display: none;">

                    <div class="alert" id="summaryBox"></div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0" id="missingTable">
                            <thead>
                                <tr>
                                    <th>Order Invoice #</th>
                                    <th>FBR Invoice No.</th>
                                    <th>Order Date</th>
                                    <th>Total</th>
                                </tr>
                            </thead>

                            <tbody></tbody>
                        </table>
                    </div>

                </div>

            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        document.getElementById('reconcileForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const btn = document.getElementById('submitBtn');
            const resultArea = document.getElementById('resultArea');
            const summaryBox = document.getElementById('summaryBox');
            const tbody = document.querySelector('#missingTable tbody');

            btn.disabled = true;
            btn.textContent = 'Checking...';

            const formData = new FormData(this);

            fetch("{{ route('backend.admin.fbr.reconciliation.compare') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json',
                },
                body: formData,
            })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.textContent = 'Check';

                    resultArea.style.display = 'block';

                    if (data.error) {
                        summaryBox.className = 'alert alert-danger';
                        summaryBox.textContent = data.error;
                        tbody.innerHTML = '';
                        return;
                    }

                    if (data.missing_count === 0) {
                        summaryBox.className = 'alert alert-success';
                        summaryBox.textContent =
                            `All good — checked ${data.total_orders_checked} invoice(s), all present in the FBR file.`;

                        tbody.innerHTML = '';
                        return;
                    }

                    summaryBox.className = 'alert alert-warning';
                    summaryBox.textContent =
                        `${data.missing_count} of ${data.total_orders_checked} invoice(s) are MISSING from the uploaded FBR file.`;

                    tbody.innerHTML = data.missing_invoices.map(row => `
                            <tr>
                                <td>${row.invoice_number ?? '-'}</td>
                                <td>${row.fbr_invoice_no ?? '-'}</td>
                                <td>${row.order_date ?? '-'}</td>
                                <td>${row.total ?? '-'}</td>
                            </tr>
                        `).join('');
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.textContent = 'Check';

                    resultArea.style.display = 'block';

                    summaryBox.className = 'alert alert-danger';
                    summaryBox.textContent =
                        'Something went wrong while checking. Please try again.';
                });
        });
    </script>
@endpush