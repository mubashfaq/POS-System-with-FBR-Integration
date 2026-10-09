@extends('backend.master')

@section('title', 'Edit Import')

@section('content')
    <div class="container-fluid import-page">

        <div class="card import-card">

            <div class="import-header">
                <div class="import-header-left">
                    <div class="import-header-icon">
                        <i class="fas fa-edit"></i>
                    </div>

                    <div>
                        <h4>Edit Import</h4>
                        <p>Update import and expense information</p>
                    </div>
                </div>

                <span class="badge">
                    <i class="fas fa-pencil-alt mr-1"></i>
                    Edit Import
                </span>
            </div>

            <div class="import-form">
                @include('backend.imports._form', [
                    'import' => $import,
                ])
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
