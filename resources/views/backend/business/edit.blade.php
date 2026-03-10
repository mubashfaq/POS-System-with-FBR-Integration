@extends('backend.master')

@section('title', 'Update Business')

@section('content')
<div class="card">
  <div class="card-body">
    <form action="{{ route('backend.admin.business.update',$business->id) }}" method="post" class="accountForm" enctype="multipart/form-data">
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
            <label for="business_type" class="form-label">
              Business Type
            </label>
            <select name="business_type" id="business_type" class="form-control" required>
              <option value="pos" 
                  {{ old('business_type', $business->business_type) == 'pos' ? 'selected' : '' }}>
                  POS
              </option>
          
              <option value="digital" 
                  {{ old('business_type', $business->business_type) == 'digital' ? 'selected' : '' }}>
                  Digital
              </option>
          
              <option value="both" 
                  {{ old('business_type', $business->business_type) == 'both' ? 'selected' : '' }}>
                  Both
              </option>
          </select>
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


            <div class="mb-3 col-md-6">
              <label for="email" class="form-label">Email</label>
              <input type="email" class="form-control" placeholder="Enter Email" name="email"
                value="{{ old('email', $business->email) }}">
            </div>

            <div class="mb-3 col-md-6">
              <label for="phone" class="form-label">Phone</label>
              <input type="text" class="form-control" placeholder="Enter Phone" name="phone"
                value="{{ old('phone', $business->phone) }}">
            </div>

            <div class="mb-3 col-md-6">
              <label for="currency" class="form-label">Currency</label>
              <input type="text" class="form-control" placeholder="Enter Currency" name="currency"
                value="{{ old('currency', $business->currency) }}">
            </div>

            <div class="mb-3 col-md-6">
              <label for="facebook_link" class="form-label">Facebook Link</label>
              <input type="url" class="form-control" placeholder="Enter Facebook Link" name="facebook_link"
                value="{{ old('facebook_link', $business->facebook_link) }}">
            </div>

            <div class="mb-3 col-md-6">
              <label for="whatsapp_link" class="form-label">WhatsApp Link</label>
              <input type="url" class="form-control" placeholder="Enter WhatsApp Link" name="whatsapp_link"
                value="{{ old('whatsapp_link', $business->whatsapp_link) }}">
            </div>

            <div class="mb-3 col-md-6">
              <label for="youtube_link" class="form-label">YouTube Link</label>
              <input type="url" class="form-control" placeholder="Enter YouTube Link" name="youtube_link"
                value="{{ old('youtube_link', $business->youtube_link) }}">
            </div>

            <div class="mb-3 col-md-6">
              <label for="instagram_link" class="form-label">Instagram Link</label>
              <input type="url" class="form-control" placeholder="Enter Instagram Link" name="instagram_link"
                value="{{ old('instagram_link', $business->instagram_link) }}">
            </div>

            <div class="mb-3 col-md-6">
              <label for="logo" class="form-label">Logo</label>
              <input type="file" class="form-control" name="logo" accept="image/*">
              @if($business->logo)
                  <img src="{{ asset('storage/'.$business->logo) }}" alt="Logo" width="80" class="mt-2">
                  <div class="form-check mt-2">
                      <input class="form-check-input" type="checkbox" name="remove_logo" id="remove_logo" value="1">
                      <label class="form-check-label" for="remove_logo">
                          Remove logo
                      </label>
                  </div>
              @endif
          </div>

            <div class="mb-3 col-md-6">
              <label for="address" class="form-label">Address</label>
              <input type="text" class="form-control" placeholder="Enter Address" name="address"
                value="{{ old('address', $business->address) }}">
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
