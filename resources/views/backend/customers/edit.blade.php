@extends('backend.master')

@section('title', 'Create Customer')

@section('content')
<div class="card">
  <div class="card-body">
    <form action="{{ route('backend.admin.customers.update',$customer->id) }}" method="post" class="accountForm"
      enctype="multipart/form-data">
      @method('PUT')
      @csrf
      <div class="card-body">
        <div class="row">
          <div class="mb-3 col-md-6">
            <label for="title" class="form-label">
              Name
              <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" placeholder="Enter title" name="name"
              value="{{ $customer->name }}" required>
          </div>
          <div class="mb-3 col-md-6">
            <label for="title" class="form-label">
              Phone
              <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" placeholder="Enter phone" name="phone"
              value="{{ $customer->phone }}" required>
          </div>

           <div class="mb-3 col-md-6">
            <label for="title" class="form-label">
              NTN / CNIC
              <span class="text-danger">*</span>

            </label>
            <input type="number" class="form-control" id="cnic" placeholder="Enter NTN or CNIC" name="ntn_cnic"
              value="{{ $customer->ntn_cnic }}" required >
          </div>
          <div class="mb-3 col-md-6">
            <label for="title" class="form-label">
              Province
              <span class="text-danger">*</span>

            </label>
            <input type="text" class="form-control" placeholder="Enter Province" name="province"
              value="{{ $customer->province }}" required>
          </div>

          <div class="mb-3 col-md-6">
            <label for="title" class="form-label">
              Address
              <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" placeholder="Enter Address" name="address"
              value="{{ $customer->address }}" required>
          </div>

          <div class="mb-3 col-md-6">
            <label for="title" class="form-label">
              Status
              <span class="text-danger">*</span>
            </label>
            <select name="is_registered" id="is_registered" class="form-control" required disabled>
              <option value="">Select Status</option>
              <option value="Registered" {{ $customer->is_registered == 'Registered' ? 'selected' : '' }}>Registered</option>
              <option value="Unregistered" {{ $customer->is_registered == 'Unregistered' ? 'selected' : '' }}>Unregistered</option>
            </select>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <button type="submit" class="btn action-button">Update</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection
@push('script')
<script>
$(document).ready(function () {

    $("#cnic").blur(function () {

        let cnic = $(this).val();

        let url = "{{ route('backend.admin.customers.is-registered', ':cnic') }}";
url = url.replace(':cnic', cnic);

        // API call
        $.ajax({
            url: url,
            type: "POST",
            data: {
                cnic: cnic,
                _token: "{{ csrf_token() }}"
            },
            success: function (res) {

                if (res.registered) {
                    $("#is_registered").val("Registered");
                } else {
                    $("#is_registered").val("Unregistered");
                }

            },
            error: function () {
                console.log("Error aagya");
            }
        });

    });

});

</script>
@endpush