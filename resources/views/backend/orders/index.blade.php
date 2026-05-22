@extends('backend.master')

@section('title', 'Sale')

@section('content')
<div class="card">
  <div class="mt-n5 mb-3 d-flex justify-content-end align-items-center">

    <input type="hidden" id="start_date">
    <input type="hidden" id="end_date">

    <button id="download-selected-btn" class="btn action-button mx-2" disabled title="Download Selected as PDF">
      <i class="fas fa-file-pdf"></i> Download PDF
      <span id="selected-count" class="badge bg-white text-danger ms-1">0</span>
    </button>

    <div class="form-group mb-0">
      <div class="input-group">
        <button type="button" class="btn btn-default float-right" id="daterange-btn">
          <i class="far fa-calendar-alt"></i> Filter by date
          <i class="fas fa-caret-down"></i>
        </button>
      </div>
    </div>
  </div>

  <div class="card-body p-2 p-md-4 pt-0">
    <div class="row g-4">
      <div class="col-md-12">
        <div class="card-body table-responsive p-0" id="table_data">
          <table id="datatables" class="table table-hover">
            <thead>
              <tr>
                <th data-orderable="false">
                  <input type="checkbox" id="select-all" title="Select All">
                </th>
                <th data-orderable="false">#</th>
                <th>SaleId</th>
                <th>Customer</th>
                <th>Item</th>
                <th>Sub Total {{currency()->symbol??''}}</th>
                <th>Discount {{currency()->symbol??''}}</th>
                <th>Total {{currency()->symbol??''}}</th>
                <th>Paid {{currency()->symbol??''}}</th>
                <th>Due {{currency()->symbol??''}}</th>
                <th>Type</th>
                <th>Date</th>
                <th>Status</th>
                <th>FBR Status</th>
                <th data-orderable="false">Action</th>
              </tr>
            </thead>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- FBR STATUS MODAL  -->
<div id="fbrStatusModal" class="modal fade" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">FBR Status</h5>
        <button type="button" class="btn btn-light btn-sm rounded-circle shadow-sm" data-bs-dismiss="modal" aria-label="Close" style="position: absolute; top: 10px; right: 10px;">
          <i class="fas fa-times text-dark"></i>
        </button>
      </div>
      <div class="modal-body">
        <p id="fbrStatusMessage"></p>
      </div>
    </div>
  </div>
</div>
<!-- /END MODAL -->

@endsection

@push('script')
<script type="text/javascript">

    let table;

    $(function () {

        // Default Dates
        let startDate = moment().subtract(29, 'days').format('YYYY-MM-DD');
        let endDate   = moment().format('YYYY-MM-DD');

        $('#start_date').val(startDate);
        $('#end_date').val(endDate);

        // DataTable
        table = $('#datatables').DataTable({
            processing: true,
            serverSide: true,
            ordering: true,
            order: [[2, 'desc']], // shifted by 1 for checkbox column

            ajax: {
                url: "{{ route('backend.admin.orders.index') }}",
                data: function (d) {
                    d.start_date = $('#start_date').val();
                    d.end_date   = $('#end_date').val();
                }
            },

            columns: [
                // Checkbox column
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row) {
                        return '<input type="checkbox" class="row-checkbox" data-id="' + row.id + '">';
                    }
                },
                { data: 'DT_RowIndex', name: 'DT_RowIndex' },
                { data: 'saleId',      name: 'saleId' },
                { data: 'customer',    name: 'customer' },
                { data: 'item',        name: 'item' },
                { data: 'sub_total',   name: 'sub_total' },
                { data: 'discount',    name: 'discount' },
                { data: 'total',       name: 'total' },
                { data: 'paid',        name: 'paid' },
                { data: 'due',         name: 'due' },
                { data: 'type',        name: 'type' },
                { data: 'date',        name: 'date' },
                { data: 'status',      name: 'status' },
                {
                    data: 'fbr_status',
                    name: 'fbr_status',
                    orderable: false,
                    searchable: false
                },
                { data: 'action',      name: 'action' }
            ]
        });

        // --- Select All (current page only) ---
        $('#select-all').on('change', function () {
            let checked = $(this).is(':checked');
            $('#datatables tbody .row-checkbox').prop('checked', checked);
            updateDownloadButton();
        });

        // --- Individual checkbox ---
        $('#datatables').on('change', '.row-checkbox', function () {
            let total   = $('#datatables tbody .row-checkbox').length;
            let checked = $('#datatables tbody .row-checkbox:checked').length;
            $('#select-all').prop('indeterminate', checked > 0 && checked < total);
            $('#select-all').prop('checked', checked === total);
            updateDownloadButton();
        });

        // Reset checkboxes on page redraw
        table.on('draw', function () {
            $('#select-all').prop('checked', false).prop('indeterminate', false);
            updateDownloadButton();
        });

        // --- Download Button ---
        $('#download-selected-btn').on('click', function () {
            let ids = getSelectedIds();
            if (ids.length === 0) return;

            // Build a form POST so IDs are sent safely
            let form = $('<form>', {
                method: 'POST',
                action: "{{ route('backend.admin.orders.pdf-download') }}",
                // target: '_blank'
            });

            form.append($('<input>', {
                type: 'hidden',
                name: '_token',
                value: "{{ csrf_token() }}"
            }));

            ids.forEach(function (id) {
                form.append($('<input>', { type: 'hidden', name: 'ids[]', value: id }));
            });

            $('body').append(form);
            form.submit();
            form.remove();
        });

        // --- Date Range Picker ---
        $('#daterange-btn').daterangepicker({
            ranges: {
                'Today':      [moment(), moment()],
                'Yesterday':  [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days':[moment().subtract(6, 'days'), moment()],
                'Last 30 Days':[moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1,'month').startOf('month'), moment().subtract(1,'month').endOf('month')]
            },
            startDate: moment(startDate, 'YYYY-MM-DD'),
            endDate:   moment(endDate,   'YYYY-MM-DD')
        }, function (start, end) {
            $('#start_date').val(start.format('YYYY-MM-DD'));
            $('#end_date').val(end.format('YYYY-MM-DD'));
            table.draw();
        });

        // FBR STATUS MODAL
        $(document).on('click', '.fbr-status', function (e) {
            e.preventDefault();
            let id = $(this).data('id');
            $.ajax({
                url: "{{ route('backend.admin.orders.fbr-status', ':id') }}".replace(':id', id),
                type: 'GET',
                dataType: 'json',
                success: function (response) {
                    $('#fbrStatusMessage').html(response.message);
                    $('#fbrStatusModal').modal('show');
                },
                error: function (xhr, status, error) {
                    console.error('Error fetching FBR status:', error);
                }
            });
        });

    });

    function getSelectedIds() {
        return $('#datatables tbody .row-checkbox:checked').map(function () {
            return $(this).data('id');
        }).get();
    }

    function updateDownloadButton() {
        let count = getSelectedIds().length;
        $('#selected-count').text(count);
        $('#download-selected-btn').prop('disabled', count === 0);
    }

</script>
@endpush