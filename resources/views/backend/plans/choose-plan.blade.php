@extends('backend.master')

@section('title', 'Plans')

@section('content')
    @push('style')
        <style>
            body {
                font-family: 'Poppins', sans-serif;
                background: #ffffff;
                color: #000;
                overflow-x: hidden;
            }

            .btn-primary-custom {
                background: #ff6600;
                color: #fff;
                padding: 14px 32px;
                border-radius: 40px;
                font-weight: 500;
                border: none;
            }

            .btn-primary-custom:hover {
                background: #000;
                color: #fff;
            }

            .btn-outline-custom {
                border: 2px solid #000;
                padding: 12px 30px;
                border-radius: 40px;
                font-weight: 600;
                margin-left: 10px;
            }

            .btn-outline-custom:hover {
                background: #000;
                color: #fff;
            }

            /* Pricing Section */
            .pricing {
                padding: 60px 0 120px;
            }

            .pricing-card {
                background: #fff;
                border-radius: 20px;
                padding: 45px 35px;
                border: 1px solid #eee;
                transition: 0.3s ease;
                position: relative;
            }

            .pricing-card:hover {
                border-color: #ff6600;
                transform: translateY(-6px);
            }

            .popular {
                border: 2px solid #ff6600;
            }

            .badge-popular {
                background: #ff6600;
                color: #fff;
                padding: 6px 18px;
                border-radius: 30px;
                font-size: 13px;
                font-weight: 600;
                position: absolute;
                top: -15px;
                right: 30px;
                z-index: 10;
            }

            .price {
                font-size: 42px;
                font-weight: 800;
                color: #ff6600;
                margin: 20px 0;
            }

            .price-features li {
                margin-bottom: 12px;
            }

            .pricing-note {
                color: #6b7280;
                font-size: 13px;
            }

            /* CURRENT PLAN BUTTON */
            .current-plan-btn {
                background: #1f2937;
                color: #fff;
                padding: 12px 28px;
                border-radius: 40px;
                font-weight: 500;
                border: none;
                cursor: not-allowed;
                opacity: 0.9;
                transition: 0.3s ease;
            }

            .current-plan-btn:hover {
                background: #111827;
            }
        </style>
    @endpush



    <div class="text-center">
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

    <!-- PRICING -->
    <section class="pricing">
        <div class="container">
            <div class="row g-4">

                @foreach($plans as $plan)

                    @php
                        $isCurrent = $currentPlan && $currentPlan->id === $plan->id;
                        $isPopular = $plan->slug === 'standard';
                    @endphp

                    <div class="col-lg-4 mb-3">

                        <div class="pricing-card text-center  {{ ($isPopular || $isCurrent) ? 'position-relative' : '' }} {{ $isPopular ? 'popular' : '' }}">

                            {{-- Popular Badge --}}
                            @if($isPopular)
                                <span class="badge-popular">Most Popular</span>
                            @endif

                            {{-- Current Plan Badge --}}
                            @if($isCurrent)
                                <div class="position-absolute" style="top: 10px; left: 10px;">
                                    <span class="badge bg-primary">Current Plan</span>
                                </div>
                            @endif

                            <h4>{{ $plan->name }}</h4>

                            <div class="price">Rs {{ number_format($plan->price, 2) }}</div>

                            <p class="text-muted">Billed Monthly</p>

                            <ul class="list-unstyled price-features mt-4">
                                @php $combined = $plan->combinedInvoiceLimit(); @endphp
                                <li>✔  {{ $combined === -1 ? 'Unlimited' : $combined }}  Invoices</li>
                                {{-- <li>✔ POS Billing</li> --}}
                                {{-- <li>✔ Basic Reports</li> --}}
                                <li>
                                    ✔ {{ $plan->limitLabel('max_users') }}
                                    User{{ $plan->max_users !== 1 ? 's' : '' }}
                                </li>
                                {{-- <li>✔ Email Support</li> --}}

                                {{-- @foreach($plan->features as $label)
                                <li>
                                    ✔ <span>{{ $label }}</span>
                                </li>
                                @endforeach --}}
                            </ul>

                            @hasrole('super_admin')
                                @if ($usage)
                                    
                                    @if($isCurrent)
                                        <button class="current-plan-btn mt-3" disabled>
                                            ✓ Current Plan
                                        </button>
                                    @else
                                        <form action="{{ route('backend.admin.subscription.change-plan.update') }}" method="POST" id="createSubForm-{{ $plan->id }}">
                                            @csrf
                                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                            
                                            <button type="submit" class="btn btn-primary-custom mt-3">
                                                Change to {{ $plan->name }}
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    <form action="{{ route('backend.admin.subscription.store') }}" method="POST" id="createSubForm-{{ $plan->id }}">
                                        @csrf
                                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                        
                                        <button type="submit" class="btn btn-primary-custom mt-3">
                                            Assign Plan
                                        </button>
                                    </form>
                                @endif
                            @else
                                @if ($usage)
                                    
                                    @if($isCurrent)

                                        <button class="current-plan-btn mt-3" disabled>
                                            ✓ Current Plan
                                        </button>

                                    @else

                                        @php
                                            $isDowngrade = $currentPlan && $plan->price < $currentPlan->price;
                                        @endphp

                                        <a href="https://wa.me/923235741450?text={{ urlencode('Hello, I want to ' . ($isDowngrade ? '⬇ Downgrade to ' : '⬆ Upgrade to ') . $plan->name . ' Package') }}"
                                            target="_blank" class="btn btn-primary-custom mt-3">

                                            {{ $isDowngrade ? '⬇ Downgrade' : '⬆ Upgrade' }} to {{ $plan->name }}

                                        </a>

                                    @endif
                                @else

                                    <a href="https://wa.me/923235741450?text={{ urlencode('Hello, I need ' . $plan->name . ' Package') }}"
                                        target="_blank" class="btn btn-primary-custom mt-3">
                                        Get Started
                                    </a>

                                @endif

                            @endhasrole
                            

                        </div>
                    </div>

                @endforeach

            </div>
        </div>

        @if($usage)
            <div class="row justify-content-center mt-5">
                <div class="col-md-8">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Your Current Usage This Month</h6>
                            <div class="row text-center">
                                @if ($businessType == 'both')
                                    <div class="col-4">
                                        <div class="fs-4 fw-bold text-primary">{{ $usage['invoices']['pos']['used'] }}</div>
                                        <small class="text-muted">
                                            of {{ $usage['invoices']['pos']['unlimited'] ? '∞' : $usage['invoices']['pos']['limit'] }} POS invoices
                                        </small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fs-4 fw-bold text-primary">{{ $usage['invoices']['digital']['used'] }}</div>
                                        <small class="text-muted">
                                            of {{ $usage['invoices']['digital']['unlimited'] ? '∞' : $usage['invoices']['digital']['limit'] }} Digital invoices
                                        </small>
                                    </div> 
                                @else
                                    <div class="col-6">
                                        <div class="fs-4 fw-bold text-primary">{{ $usage['invoices']['used'] }}</div>
                                        <small class="text-muted">
                                            of {{ $usage['invoices']['unlimited'] ? '∞' : $usage['invoices']['limit'] }} invoices
                                        </small>
                                    </div>
                                @endif
                                
                                <div class="col-4">
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

        <div class="text-center mt-4">
            <small class="pricing-note">
                * FBR integration is not included in these packages.
                Please contact us separately for FBR integration services.
            </small>
        </div>
    </section>


@endsection