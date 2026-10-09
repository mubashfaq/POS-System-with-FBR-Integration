@extends('backend.master')

@section('title', 'Import Godowns')

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

    <div class="container-fluid">

        <div class="card">

            <div class="mt-n5 mb-3 d-flex justify-content-end">
                <button type="button" class="btn btn-flat" id="addGodownBtn">
                    <i class="fas fa-plus mr-1"></i>
                    Add Godown
                </button>
            </div>

            <div class="card-body">

                <table id="godownTable" class="table table-hover">

                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Godown</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                </table>

            </div>

        </div>

    </div>


    {{-- ============================= --}}
    {{-- Godown Modal --}}
    {{-- ============================= --}}
    <div class="modal fade" id="godownModal" tabindex="-1" role="dialog" aria-labelledby="godownModalTitle"
        aria-hidden="true">

        <div class="modal-dialog" role="document">

            <div class="modal-content">

                <form id="godownForm">

                    @csrf

                    <input type="hidden" id="godown_id" name="godown_id">

                    <div class="modal-header">
                        <h5 class="modal-title" id="godownModalTitle">Add Godown</h5>

                        <button type="button" class="close js-close-modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">

                        <div class="form-group">
                            <label>Godown Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="godown_name" class="form-control"
                                placeholder="e.g. Main Warehouse" required>
                        </div>

                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location" id="godown_location" class="form-control"
                                placeholder="e.g. Karachi, Plot 12-B">
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" name="status" value="1"
                                    id="godown_status" checked>
                                <label class="custom-control-label" for="godown_status">Active</label>
                            </div>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary js-close-modal">Cancel</button>

                        <button type="submit" class="btn btn-flat" id="saveGodownBtn">
                            <i class="fas fa-save mr-1"></i> Save
                        </button>
                    </div>

                </form>

            </div>

        </div>

    </div>

@endsection


@push('script')
    <script>
        $(document).ready(function() {

            let table = $('#godownTable').DataTable({

                processing: true,
                serverSide: true,

                ajax: "{{ route('backend.admin.import-godown.index') }}",

                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'name',
                        name: 'name'
                    },
                    {
                        data: 'location',
                        name: 'location',
                        defaultContent: '<span class="text-muted">—</span>'
                    },
                    {
                        data: 'status',
                        name: 'status',
                        orderable: false
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    }
                ],

                order: [
                    [1, 'asc']
                ]

            });


            /*
            |--------------------------------------------------------------------------
            | Add Godown
            |--------------------------------------------------------------------------
            */

            $('#addGodownBtn').on('click', function() {

                $('#godownForm')[0].reset();

                $('#godown_id').val('');
                $('#godown_location').val('');
                $('#godown_status').prop('checked', true);

                $('#godownModalTitle').text('Add Godown');

                $('#godownModal').modal('show');

            });


            /*
            |--------------------------------------------------------------------------
            | Edit Godown
            |--------------------------------------------------------------------------
            */

            $('#godownTable').on('click', '.edit-godown', function() {

                let id = $(this).data('id');
                let name = $(this).data('name');
                let location = $(this).data('location');
                let status = $(this).data('status');

                $('#godown_id').val(id);
                $('#godown_name').val(name);
                $('#godown_location').val(location || '');
                $('#godown_status').prop('checked', status == 1);

                $('#godownModalTitle').text('Edit Godown');

                $('#godownModal').modal('show');

            });


            /*
            |--------------------------------------------------------------------------
            | Save / Update
            |--------------------------------------------------------------------------
            */

            $(document).on('click', '[data-dismiss="modal"], .js-close-modal', function(e) {
                e.preventDefault();
                $(this).closest('.modal').modal('hide');
            });

            $('#godownForm').on('submit', function(e) {

                e.preventDefault();

                let id = $('#godown_id').val();

                let url = id ?
                    "{{ url('admin/import-godown') }}/" + id :
                    "{{ route('backend.admin.import-godown.store') }}";

                let method = id ? 'PUT' : 'POST';

                // Because we use PUT, Laravel needs _method
                let formData = $(this).serializeArray();
                if (id) {
                    formData.push({
                        name: '_method',
                        value: 'PUT'
                    });
                }

                $.ajax({

                    url: url,
                    type: 'POST', // always POST; _method handles PUT
                    data: formData,

                    success: function(response) {

                        $('#godownModal').modal('hide');

                        table.ajax.reload(null, false);

                        if (typeof showToast === 'function') {
                            showToast(response.message, 'success');
                        } else {
                            alert(response.message);
                        }

                    },

                    error: function(xhr) {

                        let message = xhr.responseJSON?.message || 'Something went wrong.';

                        if (typeof showToast === 'function') {
                            showToast(message, 'error');
                        } else {
                            alert(message);
                        }

                    }

                });

            });

        });
    </script>
@endpush
