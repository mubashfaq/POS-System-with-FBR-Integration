@extends('backend.master')

@section('title', 'Subscription Cancelled')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">

            <div class="mb-4">
                <div class="bg-danger bg-opacity-10 d-inline-flex align-items-center justify-content-center rounded-circle"
                     style="width: 80px; height: 80px;">
                     <i class="fas fa-times-circle fs-2"></i>
                </div>
            </div>

            <h4 class="fw-bold mb-2">Subscription Cancelled</h4>
            <p class="text-muted mb-4">
                Your subscription has been cancelled. You still have access to your data
                (invoices, products) but plan features are now disabled.
            </p>

            <div class="card border-0 bg-light mb-4">
                <div class="card-body py-3 px-4 text-start">
                    <div class="small fw-semibold mb-2">What happens now:</div>
                    <ul class="small text-muted mb-0">
                        <li>Your invoices and products are safe — nothing is deleted.</li>
                        <li>You cannot create new invoices until you reactivate.</li>
                        {{-- <li>Features like reports and exports are disabled.</li> --}}
                        <li>You can reactivate any time by choosing a new plan.</li>
                    </ul>
                </div>
            </div>

            <div class="d-flex justify-content-center">
                <a href="{{ route('backend.admin.plans.choose') }}" class="btn action-button px-4 mr-2">
                    Reactivate Subscription
                </a>
                <a href="{{ route('backend.admin.dashboard') }}" class="btn btn-outline-secondary px-4">
                    Go to Dashboard
                </a>
            </div>

        </div>
    </div>
</div>
@endsection