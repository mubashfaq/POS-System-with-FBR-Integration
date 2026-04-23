@include('frontend.layouts.header', [
    'pageTitle' => 'Pricing Plans',
    'metaDescription' => 'Explore affordable pricing plans for our digital invoicing and POS system. Choose the best plan for your business.',
    'metaKeywords' => 'pricing plans, POS pricing, invoicing software cost, subscription plans'
])

<div class="container py-5">

    {{-- Heading --}}
    <div class="text-center mb-5">
        <h2 class="fw-bold">Choose Your Plan</h2>
        <p class="text-muted">Invoice limits reset every month. Upgrade anytime — new limits apply immediately.</p>

        @if($usage)
            <p class="text-muted small">
                Your billing period resets
                <strong>{{ \Carbon\Carbon::parse($usage['period']['end'])->diffForHumans() }}</strong>
                ({{ \Carbon\Carbon::parse($usage['period']['end'])->format('M d, Y') }})
            </p>
        @endif
    </div>

    {{-- Plans Grid --}}
    <div class="row justify-content-center g-4">
        @foreach($plans as $plan)
            @php
                $isCurrent   = $currentPlan && $currentPlan->id === $plan->id;
                $isPopular    = $plan->slug === 'standard';
            @endphp

            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-{{ $isCurrent ? 'primary border-2' : '0' }} position-relative">

                    {{-- Popular Badge --}}
                    @if($isPopular)
                        <div class="position-absolute top-0 start-50 translate-middle">
                            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">⭐ Most Popular</span>
                        </div>
                    @endif

                    {{-- Current Plan Badge --}}
                    {{-- @if($isCurrent)
                        <div class="position-absolute top-0 end-0 m-2">
                            <span class="badge bg-primary">Current Plan</span>
                        </div>
                    @endif --}}

                    <div class="card-body p-4 {{ $isPopular ? 'pt-5' : '' }}">

                        {{-- Plan Name & Price --}}
                        <h4 class="fw-bold">{{ $plan->name }}</h4>
                        <div class="mb-3">
                            @if($plan->price == 0)
                                <span class="fs-2 fw-bold">Free</span>
                            @else
                                <span class="fs-2 fw-bold">${{ number_format($plan->price, 2) }}</span>
                                <span class="text-muted">/month</span>
                            @endif
                        </div>

                        {{-- Limits --}}
                        <ul class="list-unstyled mb-4">

                            {{-- Invoices --}}
                            <li class="py-2 border-bottom">
                                <i class="bi bi-receipt text-primary me-2"></i>
                                <strong>{{ $plan->limitLabel('max_invoices') }}</strong>
                                invoices/month
                                <small class="text-muted d-block ms-4">POS, Digital & Both — shared limit</small>
                            </li>

                            {{-- Users --}}
                            <li class="py-2 border-bottom">
                                <i class="bi bi-people text-primary me-2"></i>
                                <strong>{{ $plan->limitLabel('max_users') }}</strong>
                                user{{ $plan->max_users !== 1 ? 's' : '' }}
                            </li>

                            {{-- Products --}}
                            {{-- <li class="py-2 border-bottom">
                                <i class="bi bi-box text-primary me-2"></i>
                                <strong>{{ $plan->limitLabel('max_products') }}</strong>
                                products
                            </li> --}}

                            {{-- Features --}}
                            {{-- @php
                                $allFeatures = ['reports' => 'Reports', 'exports' => 'Exports', 'barcode' => 'Barcode Scanner'];
                            @endphp
                            @foreach($allFeatures as $key => $label)
                                <li class="py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                                    @if($plan->hasFeature($key))
                                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                                        <span>{{ $label }}</span>
                                    @else
                                        <i class="bi bi-x-circle text-muted me-2"></i>
                                        <span class="text-muted">{{ $label }}</span>
                                    @endif
                                </li>
                            @endforeach --}}

                        </ul>

                        {{-- Action Button --}}
                        @auth
                            @if($isCurrent)
                                <button class="btn btn-outline-primary w-100" disabled>✓ Current Plan</button>
                            @else

                                    @php
                                        $isDowngrade = $currentPlan && $plan->price < $currentPlan->price;
                                    @endphp

                                    <button
                                        type="submit"
                                        class="btn w-100 {{ $isPopular ? 'btn-warning' : 'btn-primary' }}"
                                        onclick="return confirm('{{ $isDowngrade ? 'Downgrade to ' . $plan->name . '? Your usage count will reset to 0 but the new lower limits apply immediately.' : 'Upgrade to ' . $plan->name . '? Your new limits will be active immediately with a fresh usage count.' }}')"
                                    >
                                        {{ $isDowngrade ? '⬇ Downgrade' : '⬆ Upgrade' }} to {{ $plan->name }}
                                    </button>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="btn btn-primary w-100">Get Started</a>
                        @endauth

                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Current usage summary (shown when logged in) --}}
    @if($usage)
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Your Current Usage This Month</h6>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="fs-4 fw-bold text-primary">{{ $usage['invoices']['used'] }}</div>
                                <small class="text-muted">
                                    of {{ $usage['invoices']['unlimited'] ? '∞' : $usage['invoices']['limit'] }} invoices
                                </small>
                            </div>
                            <div class="col-6">
                                <div class="fs-4 fw-bold text-primary">{{ $usage['users']['used'] }}</div>
                                <small class="text-muted">
                                    of {{ $usage['users']['unlimited'] ? '∞' : $usage['users']['limit'] }} users
                                </small>
                            </div>
                            {{-- <div class="col-4">
                                <div class="fs-4 fw-bold text-primary">{{ $usage['products']['used'] }}</div>
                                <small class="text-muted">
                                    of {{ $usage['products']['unlimited'] ? '∞' : $usage['products']['limit'] }} products
                                </small>
                            </div> --}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
@include('frontend.layouts.footer')