@extends('backend.master')

@section('title', 'Subscription Expired')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">

            <div class="mb-4">
                <div class="bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center rounded-circle"
                     style="width: 80px; height: 80px;">
                     <i class="fas fa-clock fs-2"></i>
                </div>
            </div>

            <h4 class="fw-bold mb-2">Subscription Expired</h4>
            <p class="text-muted mb-4">
                Your subscription has expired. Renew now to continue creating invoices
                and accessing your POS features.
            </p>

            <div class="card border-0 bg-light mb-4">
                <div class="card-body py-3 px-4 text-start">
                    <ul class="small text-muted mb-0">
                        <li>Your data is safe — nothing has been deleted.</li>
                        <li>Select any plan below to reactivate immediately.</li>
                        <li>Your limits will reset fresh from today.</li>
                    </ul>
                </div>
            </div>

            <a href="{{ route('backend.admin.plans') }}" class="btn action-button px-5">
                Renew Subscription →
            </a>

        </div>
    </div>
</div>
@endsection