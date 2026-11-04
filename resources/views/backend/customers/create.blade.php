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
                                NTN / CNIC
                                <span class="text-danger">*</span>

                            </label>
                            <input type="text" class="form-control" id="cnic" placeholder="Enter NTN or CNIC"
                                name="ntn_cnic" value="{{ old('ntn_cnic') }}" required>
                        </div>


                        <div class="mb-3 col-md-6">
                            <label for="title" class="form-label">
                                Province
                                <span class="text-danger">*</span>

                            </label>
                            <input type="text" class="form-control" placeholder="Enter Province" name="province"
                                value="{{ old('province') }}" required>
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
                                <option value="Registered">
                                    Registered</option>
                                <option value="Unregistered">
                                    Unregistered (4% further tax)</option>
                                <option value="Endconsumer">
                                    End Consumer</option>
                            </select>
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="sale_tax_number" class="form-label"> Sale Tax Number </label>
                            <input type="text" class="form-control" placeholder="Enter Sale Tax Number"
                                name="sale_tax_number" value="{{ old('sale_tax_number') }}">
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="title" class="form-label">
                                Customer Tax Type
                            </label>
                            <select name="customer_tax_type" id="customer_tax_type" class="form-control">
                                <option value="">Select Tax Type</option>
                                <option value="retailer">Retailer (0.5% Customer Tax)</option>
                                <option value="distributor">Distributor (1% Customer Tax)</option>
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
        $(document).ready(function() {

            $("#cnic").blur(function() {

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
                    success: function(res) {

                        if (res.registered) {
                            $("#is_registered").val("Registered");
                        } else {
                            $("#is_registered").val("Unregistered");
                        }

                    },
                    error: function() {
                        console.log(
                            "There is an issue for checking customer registration status.");
                    }
                });

            });

        });
    </script>
@endpush
