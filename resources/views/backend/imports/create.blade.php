@extends('backend.master')

@section('title', 'Create Import')

@section('content')
    <div class="container-fluid import-page">

        <div class="card import-card">

            <div class="import-header">
                <div class="import-header-left">
                    <div class="import-header-icon">
                        <i class="fas fa-file-import"></i>
                    </div>

                    <div>
                        <h4>Create Import</h4>
                        <p>Add a new import record and expense details</p>
                    </div>
                </div>

               
            </div>

            <div class="import-form">
                @include('backend.imports._form')
            </div>

        </div>

    </div>
@endsection

@push('script')
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap4'
            });
        });
    </script>
@endpush
