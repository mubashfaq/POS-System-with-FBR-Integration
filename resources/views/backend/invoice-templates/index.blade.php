@extends('backend.master')
@section('title', 'Invoice Templates')

@section('content')
    <div class="card">
        <div class="mt-n5 mb-3 d-flex justify-content-end">
            <a href="{{ route('backend.admin.invoice-templates.create') }}" class="btn  action-button">
                <i class="fas fa-plus-circle"></i>
                Add New
            </a>
        </div>
        <div class="card-body">

            <div class="table-responsive">
                <table id="invoice-templates-table" class="table table-hover align-middle w-100">

                    <thead>
                        <tr>
                            <th> #</th>
                            <th>Preview</th>
                            <th>Name</th>
                            <th>Key</th>
                            <th>View Path</th>
                            <th>Status</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('style')
    <style>
        #invoice-templates-table td {
            vertical-align: middle;
        }

        #invoice-templates-table .dropdown-item {
            cursor: pointer;
        }

        #invoice-templates-table .dropdown-menu {
            min-width: 150px;
        }
    </style>
@endpush

@push('script')
    <script>
        $(function() {
            $('#invoice-templates-table').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                autoWidth: false,
                pageLength: 10,
                order: [
                    [2, 'asc']
                ],
                ajax: '{{ route('backend.admin.invoice-templates.index') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'image',
                        name: 'image',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'name',
                        name: 'name'
                    },
                    {
                        data: 'key',
                        name: 'key'
                    },
                    {
                        data: 'view',
                        name: 'view'
                    },
                    {
                        data: 'is_active',
                        name: 'is_active',
                        searchable: false
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    }
                ],
                language: {
                    emptyTable: 'No invoice templates found.',
                    processing: 'Loading templates...'
                }
            });
        });
    </script>
@endpush
