@extends('backend.master')

@section('title', 'Import Stock')

@section('content')
    <div class="card">
        <div class="card-body">

            <div class="alert alert-info">
                <strong>Instructions:</strong>
                <ul>
                    <li>File must be CSV or Excel format (xlsx, xls)</li>
                    <li>Required columns: <strong>hs_code</strong>, <strong>quantity</strong></li>
                    <li>Products will be matched using HS Code</li>
                    <li>Stock will be increased by the quantity specified</li>
                </ul>
            </div>

            <form action="{{ route('backend.admin.purchase.import.process') }}" method="POST" class="accountForm"
                enctype="multipart/form-data">
                @csrf

                <div class="row">
                    <div class="mb-3 col-md-6">
                        <div class="form-group">
                            <label for="stockFileInput">File input</label>

                            <div class="input-group">
                                <div class="custom-file">
                                    <input type="file"
                                        class="custom-file-input @error('stock_file') is-invalid @enderror"
                                        name="stock_file" id="stockFileInput" accept=".csv,.xlsx,.xls" required>

                                    <label class="custom-file-label" for="stockFileInput">
                                        Choose file
                                    </label>
                                </div>

                                <div class="input-group-append">
                                    <a class="input-group-text"
                                        href="{{ route('backend.admin.purchase.import.process', ['download-demo' => 1]) }}">
                                        <i class="fas fa-download"></i> Demo
                                    </a>
                                </div>

                            </div>

                            @error('stock_file')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror

                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="mb-3 col-md-6">
                        <button type="submit" class="btn btn-block action-button">
                            <i class="fas fa-upload"></i> Import Stock
                        </button>
                    </div>
                </div>

            </form>

        </div>
    </div>
@endsection

@push('style')
    <style>
        .select2-container--default .select2-selection--single {
            height: calc(1.5em + 0.75rem + 2px) !important;
        }
    </style>
@endpush

@push('script')
    <script>
        document.querySelector('.custom-file-input').addEventListener('change', function(e) {
            var fileName = e.target.files[0].name;
            e.target.nextElementSibling.innerText = fileName;
        });
    </script>
@endpush
