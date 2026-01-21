@extends('backend.master')

@section('title', 'Create Business')

@section('content')
<div class="card">
  <div class="card-body">
    <form action="{{ route('backend.admin.business.store') }}" method="post" class="accountForm">
      @csrf
      <div class="card-body">
        <div class="row">
          <!-- Business Name -->
          <div class="mb-3 col-md-6">
            <label for="business_name" class="form-label">
              Business Name <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" id="business_name" placeholder="Enter business name" 
              name="business_name" value="{{ old('business_name') }}" required>
          </div>

          <!-- Industry -->
          <div class="mb-3 col-md-6">
            <label for="industry" class="form-label">
              Industry
            </label>
            <input type="text" class="form-control" id="industry" placeholder="Enter industry" 
              name="industry" value="{{ old('industry') }}">
          </div>

          <!-- NTN -->
          <div class="mb-3 col-md-6">
            <label for="ntn" class="form-label">
              NTN
            </label>
            <input type="text" class="form-control" id="ntn" placeholder="Enter NTN" 
              name="ntn" value="{{ old('ntn') }}">
          </div>

          <!-- CNIC -->
          <div class="mb-3 col-md-6">
            <label for="cnic" class="form-label">
              CNIC
            </label>
            <input type="text" class="form-control" id="cnic" placeholder="Enter CNIC" 
              name="cnic" value="{{ old('cnic') }}">
          </div>

          <!-- Country -->
        <div class="mb-3 col-md-6">
            <label for="country" class="form-label">Country</label>
            <select name="country" id="country" class="form-control">
                <option value="">-- Select Country --</option>
                @foreach($countries as $code => $country)
                    <option value="{{ $country['name']['common'] }}" 
                        {{ old('country') == $country['name']['common'] ? 'selected' : '' }}>
                        {{ $country['name']['common'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- state -->
         <div class="mb-3 col-md-6">
            <label for="state" class="form-label">State</label>
            <input type="text" class="form-control" id="state" placeholder="Enter state" 
              name="state" value="{{ old('state') }}">
                        {{ $country['name']['common'] }}
                    </option>
                @endforeach
            </select>
        </div>

          <!-- City -->
          <div class="mb-3 col-md-6">
            <label for="city" class="form-label">
              City
            </label>
            <input type="text" class="form-control" id="city" placeholder="Enter city" 
              name="city" value="{{ old('city') }}">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <button type="submit" class="btn action-button">Create</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection
