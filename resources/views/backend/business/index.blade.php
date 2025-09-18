@extends('backend.master')

@section('title', 'Businesses')

@section('content')
<div class="card">

  @can('business_create')
  <div class="mt-n5 mb-3 d-flex justify-content-end">
    <a href="{{ route('backend.admin.business.create') }}" class="btn action-button">
      <i class="fas fa-plus-circle"></i>
      Add New
    </a>
  </div>
  @endcan

  <div class="card-body p-2 p-md-4 pt-0">
    <div class="row g-4">
      <div class="col-md-12">
        <div class="card-body table-responsive p-0" id="table_data">
          <table id="datatables" class="table table-hover">
            <thead>
              <tr>
                <th data-orderable="false">#</th>
                <th>Business Name</th>
                <th>Industry</th>
                <th>NTN</th>
                <th>Country</th>
                <th>City</th>
                <th data-orderable="false">Action</th>
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
<script type="text/javascript">
  $(function() {
    let table = $('#datatables').DataTable({
      processing: true,
      serverSide: true,
      ordering: true,
      order: [
        [1, 'asc']
      ],
      ajax: {
        url: "{{ route('backend.admin.business.index') }}"
      },

      columns: [
        { data: 'DT_RowIndex', name: 'DT_RowIndex' },
        { data: 'business_name', name: 'business_name' },
        { data: 'industry', name: 'industry' },
        { data: 'ntn', name: 'ntn' },
        { data: 'country', name: 'country' },
        { data: 'city', name: 'city' },
        { data: 'action', name: 'action' },
      ]
    });
  });
</script>
@endpush
