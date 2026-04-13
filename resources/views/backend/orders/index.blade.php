@extends('backend.master')

@section('title', 'Sale')

@section('content')
<div class="card">
  <div class="card-body p-2 p-md-4 pt-0">
    <div class="row g-4">
      <div class="col-md-12">
        <div class="card-body table-responsive p-0" id="table_data">
          <table id="datatables" class="table table-hover">
            <thead>
              <tr>
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
  $(function() {
    let table = $('#datatables').DataTable({
      processing: true,
      serverSide: true,
      ordering: true,
      order: [
        [1, 'desc']
      ],
      ajax: {
        url: "{{ route('backend.admin.orders.index') }}"
      },

      columns: [{
          data: 'DT_RowIndex',
          name: 'DT_RowIndex'
        },
        {
          data: 'saleId',
          name: 'saleId'
        },
        {
          data: 'customer',
          name: 'customer'
        },
        {
          data: 'item',
          name: 'item'
        },
        {
          data: 'sub_total',
          name: 'sub_total'
        },
        {
          data: 'discount',
          name: 'discount'
        },
        {
          data: 'total',
          name: 'total'
        }, 
       
         {
          data: 'paid',
          name: 'paid'
        },
         {
          data: 'due',
          name: 'due'
        },
        //  {
        //   data: 'stock_total',
        //   name: 'stock_total'
        // }, 
        {
          data: 'type',
          name: 'type'
        },
        {
          data: 'date',
          name: 'date'
        },
        {
          data: 'status',
          name: 'status'
        },
        {
          data: 'fbr_status',
          name: 'fbr_status', orderable:false, 
          searchable:false },

        {
          data: 'action',
          name: 'action'
        },
      ]
    });
  });

  $(document).on('click', '.fbr-status', function (e) {
    e.preventDefault();

    var id = $(this).data('id');
      
    $.ajax({
      url: "{{ route('backend.admin.orders.fbr-status', ':id') }}".replace(':id', id),
      type: 'GET',
      dataType: 'json',
      success: function(response) {
        console.log("fbr api response:", response);

        // Set the message returned by your Laravel controller
        $('#fbrStatusMessage').html(response.message);

        // Show the modal
        $('#fbrStatusModal').modal('show');
      },
      error: function(xhr, status, error) {
        console.error('Error fetching FBR status:', error);
      }
    });

     
  });

</script>
@endpush