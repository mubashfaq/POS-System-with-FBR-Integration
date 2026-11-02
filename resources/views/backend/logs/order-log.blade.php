@extends('backend.master')

@section('title', 'Order Logs')

@section('content')
    <div class="card">
        <div class="card-body p-2 p-md-4">
            <div class="row g-4">
                <div class="col-md-12">
                    <div class="card-body table-responsive p-0" id="table_data">
                        <table id="datatables" class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Order</th>
                                    <th>Status Code</th>
                                    <th>Result</th>
                                    <th>Error Code</th>
                                    <th>Error Message</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Response Modal --}}
    <x-backend.modal id="logModal" title="Log Details" icon="fas fa-file-alt" size="lg">
        {{-- error block --}}
        <div id="modal-error-block" class="alert alert-danger d-none">
            <div class="d-flex justify-content-between">
                <strong>Error</strong>
                <span id="modal-error-code" class="badge bg-danger"></span>
            </div>
            <p id="modal-error-message" class="mb-0 mt-1 small"></p>
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <h6 class="fw-semibold mb-0">
                    <i class="fas fa-arrow-up text-primary me-1"></i> Request Payload
                </h6>
                <button class="btn btn-sm btn-outline-secondary btn-copy" data-target="request-json">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>
            <pre id="request-json" class="json-block"></pre>
        </div>

        <div>
            <div class="d-flex justify-content-between align-items-center mb-1">
                <h6 class="fw-semibold mb-0">
                    <i class="fas fa-arrow-down text-success me-1"></i> Response
                </h6>
                <button class="btn btn-sm btn-outline-secondary btn-copy" data-target="response-json">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>
            <pre id="response-json" class="json-block"></pre>
        </div>
    </x-backend.modal>
@endsection


@push('script')
    <script>
        $(function() {
            $('#datatables').DataTable({
                processing: true,
                serverSide: true,
                ordering: true,
                order: [
                    [6, 'desc']
                ], // default: newest first
                ajax: "{{ route('backend.admin.order-logs.index') }}",

                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'order_ref',
                        name: 'order_id'
                    },
                    {
                        data: 'status_code',
                        name: 'status_code'
                    },
                    {
                        data: 'success',
                        name: 'success'
                    },
                    {
                        data: 'error_code',
                        name: 'error_code'
                    },
                    {
                        data: 'error',
                        name: 'error_message',
                        orderable: false
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
                    },
                ]
            });

            // Open modal with log details
            $(document).on('click', '.btn-view-log', function() {
                const request = $(this).data('request');
                const response = $(this).data('response');
                const error = $(this).data('error');
                const code = $(this).data('code');

                // Pretty-print JSON
                $('#request-json').text(JSON.stringify(request, null, 2));
                $('#response-json').text(JSON.stringify(response, null, 2));

                // Show/hide error block
                if (error) {
                    $('#modal-error-message').text(error);
                    $('#modal-error-code').text(code || '');
                    $('#modal-error-block').removeClass('d-none');
                } else {
                    $('#modal-error-block').addClass('d-none');
                }

                $('#logModal').modal('show');
            });

            // Copy to clipboard
            $(document).on('click', '.btn-copy', function() {
                const targetId = $(this).data('target');
                const text = $('#' + targetId).text();
                navigator.clipboard.writeText(text).then(() => {
                    const btn = $(this);
                    btn.html('<i class="fas fa-check"></i> Copied!');
                    setTimeout(() => btn.html('<i class="fas fa-copy"></i> Copy'), 2000);
                });
            });
        });
    </script>
@endpush
