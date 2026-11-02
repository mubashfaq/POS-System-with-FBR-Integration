@extends('backend.master')

@section('title', 'Product Logs')

@section('content')
    <div class="card">
        <div class="card-body">
            <table class="table table-hover align-middle" id="product-logs-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Changes</th>
                        <th>Date</th>
                        <th>Details</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <x-backend.modal id="logDetailModal" title="Change Details" icon="fas fa-file-alt">
        <div id="logDetailBody">
            <div class="modal-section" id="logDetailSection"></div>
        </div>
    </x-backend.modal>
@endsection

@push('script')
    <script>
        $(function() {
            $('#product-logs-table').DataTable({
                processing: true,
                serverSide: true,
                order: [
                    [5, 'desc']
                ],
                ajax: '{{ route('backend.admin.product-logs.index') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'product',
                        name: 'product_name'
                    },
                    {
                        data: 'user',
                        name: 'user'
                    },
                    {
                        data: 'action_badge',
                        name: 'action_badge',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'changes_summary',
                        name: 'changes_summary',
                        orderable: false,
                        searchable: false
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

            $(document).on('click', '.btn-view-product-log', function() {
                const changed = $(this).data('changed') || {};
                const oldV = $(this).data('old') || {};
                const newV = $(this).data('new') || {};
                const action = $(this).data('action');

                let html = '';

                if (action === 'deleted') {
                    html =
                        '<div class="modal-section-title"><i class="fas fa-trash"></i> Deleted — Snapshot</div>' +
                        '<pre class="json-block">' + JSON.stringify(oldV, null, 2) + '</pre>';
                } else if (action === 'created') {
                    html =
                        '<div class="modal-section-title"><i class="fas fa-plus-circle"></i> Created With</div>' +
                        '<pre class="json-block">' + JSON.stringify(newV, null, 2) + '</pre>';
                } else {
                    let rows = '';
                    Object.keys(changed).forEach(field => {
                        rows += `
                <tr>
                    <td><strong>${field}</strong></td>
                    <td class="text-danger">${JSON.stringify(changed[field].old)}</td>
                    <td class="text-success">${JSON.stringify(changed[field].new)}</td>
                </tr>`;
                    });

                    html =
                        '<div class="modal-section-title"><i class="fas fa-edit"></i> Field Changes</div>' +
                        '<div class="table-responsive">' +
                        '<table class="table table-bordered table-hover table-sm mb-0">' +
                        '<thead><tr><th>Field</th><th>Old</th><th>New</th></tr></thead>' +
                        '<tbody>' + rows + '</tbody>' +
                        '</table>' +
                        '</div>';
                }

                $('#logDetailSection').html(html);
                $('#logDetailModal').modal('show');
            });
        });
    </script>
@endpush
