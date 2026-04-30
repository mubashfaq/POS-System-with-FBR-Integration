@extends('backend.master')

@section('title', 'Update Plan')

@section('content')
    <div class="container py-4">

        <div class="row justify-content-center">
            <div class="col-lg-12">

                {{-- Active subscribers warning --}}
                @php $activeCount = $plan->subscriptions()->where('status', 'active')->count(); @endphp
                @if($activeCount > 0)
                    <div class="alert alert-warning d-flex gap-3 align-items-start mb-4">
                        <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
                        <div>
                            <strong>{{ $activeCount }} business{{ $activeCount !== 1 ? 'es are' : ' is' }} currently on this
                                plan.</strong>
                            <div class="small mt-1">
                                Changes to limits take effect immediately for all subscribers.
                                Reducing limits will not remove existing data — it only restricts new creation.
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card border-0 shadow-sm">

                    <div class="card-body p-4">
                        <form action="{{ route('backend.admin.plans.update', $plan) }}" method="POST">
                            @csrf
                            @method('PUT')

                            @include('backend.plans.form')

                            <div class="d-flex justify-content-between align-items-center mt-4 pt-3">

                                <button type="submit" class="btn action-button px-4">
                                    Update
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection