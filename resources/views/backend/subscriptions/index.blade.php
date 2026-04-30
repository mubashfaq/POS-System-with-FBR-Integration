@extends('backend.master')

@section('title', 'My Subscription')

@section('content')
    @hasrole('super_admin')
    @if ($subscription)
        <div class="mt-n5 mb-3 d-flex justify-content-end">
            <div class="dropdown d-inline">
                <button class="btn action-button dropdown-toggle" type="button" data-toggle="dropdown">
                    Change Status
                </button>

                <div class="dropdown-menu">
                    <button type="button"
                        class="dropdown-item submit-status {{ $subscription->status == 'active' ? 'disabled' : '' }}"
                        data-status="active">
                        Activate
                    </button>
                    <button type="button"
                        class="dropdown-item submit-status {{ $subscription->status == 'cancelled' ? 'active' : '' }}"
                        data-status="cancelled">
                        Cancel
                    </button>
                    <button type="button"
                        class="dropdown-item submit-status {{ $subscription->status == 'expired' ? 'active' : '' }}"
                        data-status="expired">
                        Expire
                    </button>
                </div>
            </div>

            <form id="statusForm" action="{{ route('backend.admin.subscription.update-status') }}" method="POST">
                @csrf
                @method('PATCH')

                <input type="hidden" name="subscription_id" value="{{ $subscription->id }}">
                <input type="hidden" name="status" id="statusInput">
            </form>
            @if ($subscription->status == 'active')

                <a href="{{ route('backend.admin.plans.choose') }}" class="btn action-button ml-2">
                    ⬆ Upgrade Plan
                </a>
            @endif
        </div>
    @endif

    @endhasrole

    @if($usage['plan'] == null)
        <div class="card border-0 shadow-sm my-3">
            <div class="card-body text-center p-3">
                <h6 class="mb-2">No Active Plan</h6>
                <p class="text-muted small mb-3">
                    You are not subscribed to any plan. Choose a plan to start using features.
                </p>
                <a href="{{ route('backend.admin.plans.choose') }}" class="btn btn-sm action-button">
                    Subscribe Now
                </a>
            </div>
        </div>
    @else
        <div class="container py-4">

            <div class="row g-4">

                {{-- Left: Plan Info Card --}}
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div>
                                    <div class="text-muted small">Current Plan</div>
                                    <div class="fs-5 fw-bold">{{ $usage['plan']['name'] }}</div>
                                </div>
                            </div>

                            <hr>

                            <div class="mb-2 d-flex justify-content-between">
                                <span class="text-muted small">Status</span>
                                <span class="badge bg-primary">Active</span>
                            </div>
                            <div class="mb-2 d-flex justify-content-between">
                                <span class="text-muted small">Period Start</span>
                                <span class="small fw-semibold">
                                    {{ \Carbon\Carbon::parse($usage['period']['start'])->format('M d, Y') }}
                                </span>
                            </div>
                            <div class="mb-2 d-flex justify-content-between">
                                <span class="text-muted small">Period End</span>
                                <span class="small fw-semibold">
                                    {{ \Carbon\Carbon::parse($usage['period']['end'])->format('M d, Y') }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">Resets In</span>
                                <span class="small fw-semibold text-primary">
                                    {{ $usage['period']['days_remaining'] }} day(s)
                                </span>
                            </div>

                            <hr>

                            {{-- Features --}}
                            {{-- <div class="mb-2 text-muted small fw-semibold text-uppercase">Features</div>
                            @php
                            $allFeatures = ['reports' => 'Reports', 'exports' => 'Exports', 'barcode' => 'Barcode Scanner'];
                            @endphp
                            @foreach($allFeatures as $key => $label)
                            <div class="d-flex align-items-center gap-2 mb-1">
                                @if(in_array($key, $usage['plan']['features']))
                                <i class="bi bi-check-circle-fill text-success"></i>
                                @else
                                <i class="bi bi-x-circle text-muted"></i>
                                @endif
                                <span class="small {{ in_array($key, $usage['plan']['features']) ? '' : 'text-muted' }}">
                                    {{ $label }}
                                </span>
                            </div>
                            @endforeach --}}

                            <div class="mt-4 d-grid gap-2">
                                <a href="{{ route('backend.admin.plans.choose') }}" class="btn btn-outline-primary btn-sm">
                                    Change Plan
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#cancelModal">
                                    Cancel Subscription
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right: Usage Cards --}}
                <div class="col-md-8">
                    <div class="row g-3">

                        {{-- Invoices Card --}}
                        @if ($businessType == 'both')
                            <div class="col-12">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h6 class="fw-bold mb-0">POS Invoices</h6>
                                            </div>
                                            <div class="text-end">
                                                @if($usage['invoices']['pos']['unlimited'])
                                                    <span class="fs-4 fw-bold text-primary">{{ $usage['invoices']['pos']['used'] }}</span>
                                                    <span class="text-muted"> / ∞</span>
                                                @else
                                                    <span class="fs-4 fw-bold text-primary">{{ $usage['invoices']['pos']['used'] }}</span>
                                                    <span class="text-muted"> / {{ $usage['invoices']['pos']['limit'] }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Progress Bar --}}
                                        @unless($usage['invoices']['pos']['unlimited'])
                                            @php
                                                $pct = $usage['invoices']['pos']['limit'] > 0
                                                    ? min(100, round(($usage['invoices']['pos']['used'] / $usage['invoices']['pos']['limit']) * 100))
                                                    : 0;
                                                $barColor = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');
                                            @endphp
                                            <div class="progress mb-2" style="height: 10px; border-radius: 6px;">
                                                <div class="progress-bar {{ $barColor }}" style="width: {{ $pct }}%"></div>
                                            </div>
                                            <div class="text-muted small mb-3">
                                                {{ $usage['invoices']['pos']['remaining'] }} POS invoice(s) remaining this month
                                            </div>
                                        @endunless

                                        {{-- Warning if near limit --}}
                                        @if(!$usage['invoices']['pos']['unlimited'] && $usage['invoices']['pos']['remaining'] !== null)
                                            @if($usage['invoices']['pos']['remaining'] === 0)
                                                <div class="alert alert-danger py-2 mb-0 mt-3 small">
                                                    ⛔ You've reached your POS invoice limit.
                                                    <a href="{{ route('backend.admin.plans.choose') }}" class="alert-link">Upgrade now</a>
                                                    to create more invoices.
                                                </div>
                                            @elseif($usage['invoices']['pos']['remaining'] <= 2)
                                                <div class="alert alert-warning py-2 mb-0 mt-3 small">
                                                    ⚠️ Only {{ $usage['invoices']['pos']['remaining'] }} POS invoice(s) left this month.
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h6 class="fw-bold mb-0">Digital Invoices</h6>
                                            </div>
                                            <div class="text-end">
                                                @if($usage['invoices']['digital']['unlimited'])
                                                    <span class="fs-4 fw-bold text-primary">{{ $usage['invoices']['digital']['used'] }}</span>
                                                    <span class="text-muted"> / ∞</span>
                                                @else
                                                    <span class="fs-4 fw-bold text-primary">{{ $usage['invoices']['digital']['used'] }}</span>
                                                    <span class="text-muted"> / {{ $usage['invoices']['digital']['limit'] }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Progress Bar --}}
                                        @unless($usage['invoices']['digital']['unlimited'])
                                            @php
                                                $pct = $usage['invoices']['digital']['limit'] > 0
                                                    ? min(100, round(($usage['invoices']['digital']['used'] / $usage['invoices']['digital']['limit']) * 100))
                                                    : 0;
                                                $barColor = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');
                                            @endphp
                                            <div class="progress mb-2" style="height: 10px; border-radius: 6px;">
                                                <div class="progress-bar {{ $barColor }}" style="width: {{ $pct }}%"></div>
                                            </div>
                                            <div class="text-muted small mb-3">
                                                {{ $usage['invoices']['digital']['remaining'] }} Digital invoice(s) remaining this month
                                            </div>
                                        @endunless

                                        {{-- Warning if near limit --}}
                                        @if(!$usage['invoices']['digital']['unlimited'] && $usage['invoices']['digital']['remaining'] !== null)
                                            @if($usage['invoices']['digital']['remaining'] === 0)
                                                <div class="alert alert-danger py-2 mb-0 mt-3 small">
                                                    ⛔ You've reached your Digital invoice limit.
                                                    <a href="{{ route('backend.admin.plans.choose') }}" class="alert-link">Upgrade now</a>
                                                    to create more invoices.
                                                </div>
                                            @elseif($usage['invoices']['digital']['remaining'] <= 2)
                                                <div class="alert alert-warning py-2 mb-0 mt-3 small">
                                                    ⚠️ Only {{ $usage['invoices']['digital']['remaining'] }} Digital invoice(s) left this month.
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="col-12">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h6 class="fw-bold mb-0">Invoices This Month</h6>
                                            </div>
                                            <div class="text-end">
                                                @if($usage['invoices']['unlimited'])
                                                    <span class="fs-4 fw-bold text-primary">{{ $usage['invoices']['used'] }}</span>
                                                    <span class="text-muted"> / ∞</span>
                                                @else
                                                    <span class="fs-4 fw-bold text-primary">{{ $usage['invoices']['used'] }}</span>
                                                    <span class="text-muted"> / {{ $usage['invoices']['limit'] }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Progress Bar --}}
                                        @unless($usage['invoices']['unlimited'])
                                            @php
                                                $pct = $usage['invoices']['limit'] > 0
                                                    ? min(100, round(($usage['invoices']['used'] / $usage['invoices']['limit']) * 100))
                                                    : 0;
                                                $barColor = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');
                                            @endphp
                                            <div class="progress mb-2" style="height: 10px; border-radius: 6px;">
                                                <div class="progress-bar {{ $barColor }}" style="width: {{ $pct }}%"></div>
                                            </div>
                                            <div class="text-muted small mb-3">
                                                {{ $usage['invoices']['remaining'] }} invoice(s) remaining this month
                                            </div>
                                        @endunless

                                        {{-- Warning if near limit --}}
                                        @if(!$usage['invoices']['unlimited'] && $usage['invoices']['remaining'] !== null)
                                            @if($usage['invoices']['remaining'] === 0)
                                                <div class="alert alert-danger py-2 mb-0 mt-3 small">
                                                    ⛔ You've reached your invoice limit.
                                                    <a href="{{ route('backend.admin.plans.choose') }}" class="alert-link">Upgrade now</a>
                                                    to create more invoices.
                                                </div>
                                            @elseif($usage['invoices']['remaining'] <= 2)
                                                <div class="alert alert-warning py-2 mb-0 mt-3 small">
                                                    ⚠️ Only {{ $usage['invoices']['remaining'] }} invoice(s) left this month.
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                        

                        {{-- Users Card --}}
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center gap-3 mb-3">
                                        <h6 class="fw-bold mb-0">Users</h6>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-end">
                                        <div>
                                            <div class="fs-3 fw-bold">{{ $usage['users']['used'] }}</div>
                                            <small class="text-muted">
                                                of {{ $usage['users']['unlimited'] ? '∞' : $usage['users']['limit'] }} users
                                            </small>
                                        </div>
                                        @unless($usage['users']['unlimited'])
                                            <div class="text-end">
                                                <small class="text-muted">
                                                    {{ $usage['users']['remaining'] }} slot(s) left
                                                </small>
                                            </div>
                                        @endunless
                                    </div>
                                    @unless($usage['users']['unlimited'])
                                        @php
                                            $uPct = $usage['users']['limit'] > 0
                                                ? min(100, round(($usage['users']['used'] / $usage['users']['limit']) * 100))
                                                : 0;
                                        @endphp
                                        <div class="progress mt-2" style="height: 6px;">
                                            <div class="progress-bar bg-info" style="width: {{ $uPct }}%"></div>
                                        </div>
                                    @endunless
                                </div>
                            </div>
                        </div>

                        {{-- Products Card --}}
                        {{-- <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center gap-3 mb-3">
                                        <div class="bg-warning bg-opacity-10 rounded-3 p-2">
                                            <i class="bi bi-box-fill text-warning fs-5"></i>
                                        </div>
                                        <h6 class="fw-bold mb-0">Products</h6>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-end">
                                        <div>
                                            <div class="fs-3 fw-bold">{{ $usage['products']['used'] }}</div>
                                            <small class="text-muted">
                                                of {{ $usage['products']['unlimited'] ? '∞' : $usage['products']['limit'] }}
                                                products
                                            </small>
                                        </div>
                                        @unless($usage['products']['unlimited'])
                                        <div class="text-end">
                                            <small class="text-muted">
                                                {{ $usage['products']['remaining'] }} slot(s) left
                                            </small>
                                        </div>
                                        @endunless
                                    </div>
                                    @unless($usage['products']['unlimited'])
                                    @php
                                    $prPct = $usage['products']['limit'] > 0
                                    ? min(100, round(($usage['products']['used'] / $usage['products']['limit']) * 100))
                                    : 0;
                                    @endphp
                                    <div class="progress mt-2" style="height: 6px;">
                                        <div class="progress-bar bg-warning" style="width: {{ $prPct }}%"></div>
                                    </div>
                                    @endunless
                                </div>
                            </div>
                        </div> --}}

                    </div>
                </div>

            </div>
        </div>

        {{-- Cancel Confirmation Modal --}}
        <div class="modal fade" id="cancelModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-danger">Cancel Subscription?</h5>
                        <button type="button" class="close text-dark" data-bs-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">
                            Are you sure you want to cancel your <strong>{{ $usage['plan']['name'] }}</strong> plan?
                        </p>
                        <ul class="text-muted small">
                            <li>Your subscription will be marked as cancelled immediately.</li>
                            <li>You will lose access to plan features.</li>
                            <li>Your data (invoices, products) will not be deleted.</li>
                        </ul>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            Keep My Plan
                        </button>
                        <form action="{{ route('backend.admin.subscription.cancel') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-danger">
                                Yes, Cancel Subscription
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @push('script')
        <script>
            document.querySelectorAll('.submit-status').forEach(btn => {
                btn.addEventListener('click', function () {
                    const status = this.dataset.status;

                    document.getElementById('statusInput').value = status;
                    document.getElementById('statusForm').submit();
                });
            });
        </script>
    @endpush

@endsection