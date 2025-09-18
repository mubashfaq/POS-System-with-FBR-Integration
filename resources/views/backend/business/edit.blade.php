@extends('backend.master')

@section('title', 'Update Business')

@section('content')
<div class="card">
  <div class="card-body">
    <form action="{{ route('backend.admin.business.update',$business->id) }}" method="post" class="accountForm">
      @csrf
      @method('PUT')
      <div class="card-body">
        <div class="row">

          <div class="mb-3 col-md-6">
            <label for="business_name" class="form-label">
              Business Name <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" placeholder="Enter Business Name" name="business_name"
              value="{{ old('business_name', $business->business_name) }}" required>
          </div>

          <div class="mb-3 col-md-6">
            <label for="industry" class="form-label">
              Industry
            </label>
            <input type="text" class="form-control" placeholder="Enter Industry" name="industry"
              value="{{ old('industry', $business->industry) }}">
          </div>

          <div class="mb-3 col-md-6">
            <label for="ntn" class="form-label">
              NTN
            </label>
            <input type="text" class="form-control" placeholder="Enter NTN" name="ntn"
              value="{{ old('ntn', $business->ntn) }}">
          </div>

          <div class="mb-3 col-md-6">
            <label for="country" class="form-label">
              Country
            </label>
            <input type="text" class="form-control" placeholder="Enter Country" name="country"
              value="{{ old('country', $business->country) }}">
          </div>

          <div class="mb-3 col-md-6">
            <label for="city" class="form-label">
              City
            </label>
            <input type="text" class="form-control" placeholder="Enter City" name="city"
              value="{{ old('city', $business->city) }}">
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
