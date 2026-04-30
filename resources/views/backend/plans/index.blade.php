@extends('backend.master')

@section('title', 'Plans')

@section('content')
    <div class="card">

            <div class="mt-n5 mb-3 d-flex justify-content-end">
                <a href="{{ route('backend.admin.plans.create') }}" class="btn action-button">
                    <i class="fas fa-plus-circle"></i>
                    Add New
                </a>
            </div>

        <div class="card-body p-2 p-md-4 pt-0">
            <div class="row g-4">
                <div class="col-md-12">
                    <div class="card-body table-responsive p-0" id="table_data">
                        <table id="datatables" class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Plan</th>
                                    <th>Price ({{currency()->symbol??''}})</th>
                                    <th>POS Invoices</th>
                                    <th>Digital Invoices</th>
                                    <th>Total Invoices</th>
                                    <th>Users</th>
                                    {{-- <th>Products</th> --}}
                                    <th>Features</th>
                                    <th>Subscribers</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection


@push('script')
    <script>
        $(function () {
            $('#datatables').DataTable({
                processing: true,
                serverSide: true,
                ordering: true,
                ajax: "{{ route('backend.admin.plans.index') }}",

                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'name', name: 'name' },
                    { data: 'price', name: 'price' },
                    { data: 'pos_invoices', name: 'max_pos_invoices' },
                    { data: 'digital_invoices', name: 'max_digital_invoices' },
                    { data: 'total_invoices', name: 'total_invoices' },
                    { data: 'users', name: 'max_users' },
                    // { data: 'products', name: 'max_products' },
                    { data: 'features', name: 'features', orderable: false, searchable: false },
                    { data: 'subscribers', name: 'active_subscribers', orderable: false, searchable: false },
                    { data: 'status', name: 'is_active' },
                    { data: 'action', name: 'action', orderable: false, searchable: false },
                ]
            });
        });
    </script>
@endpush