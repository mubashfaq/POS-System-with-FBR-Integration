@extends('backend.master')

@section('title', 'Create Plan')

@section('content')
    <div class="container py-4">

        <div class="row justify-content-center">
            <div class="col-lg-12">

                <div class="card border-0 shadow-sm">

                    <div class="card-body p-4">
                        <form action="{{ route('backend.admin.plans.store') }}" method="POST">
                            @csrf

                            @php $plan = new \App\Models\Plan(); @endphp
                            @include('backend.plans.form')

                            <div class="d-flex justify-content-between align-items-center mt-4 pt-3">
                                
                                <button type="submit" class="btn action-button px-4">
                                    Create
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection