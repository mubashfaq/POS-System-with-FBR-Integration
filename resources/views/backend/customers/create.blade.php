@extends('backend.master')

@section('title', 'Create Customer')

@section('content')
<div class="card">
  <div class="card-body">
    <form action="{{ route('backend.admin.customers.store') }}" method="post" class="accountForm"
      enctype="multipart/form-data">
      @csrf
      <div class="card-body">
        <div class="row">
          <div class="mb-3 col-md-6">
            <label for="title" class="form-label">
              Name
              <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" placeholder="Enter title" name="name"
              value="{{ old('name') }}" required>
          </div>
          <div class="mb-3 col-md-6">
            <label for="title" class="form-label">
              Phone
              <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" placeholder="Enter phone" name="phone"
              value="{{ old('phone') }}" required>
          </div>
          <div class="mb-3 col-md-6">
            <label for="title" class="form-label">
              Address
              <span class="text-danger">*</span>

            </label>
            <input type="text" class="form-control" placeholder="Enter Address" name="address"
              value="{{ old('Address') }}" required>
          </div>

          <div class="mb-3 col-md-6">
            <label for="title" class="form-label">
              Status
            <span class="text-danger">*</span>

            </label>
            <select name="is_registered" id="is_registered" class="form-control" required>
              <option value="">Select Status</option>
              <option value="Registered" {{ old('is_registered') == 'Registered' ? 'selected' : '' }}>Registered</option>
              <option value="Unregistered" {{ old('is_registered') == 'Unregistered' ? 'selected' : '' }}>Unregistered</option>
            </select>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <button type="submit" class="btn action-button">Create</button>
          </div>
        </div>
      </div>
      <!-- /.card-body -->
    </form>
  </div>
</div>
@endsection
@push('script')
<script>
</script>
@endpush