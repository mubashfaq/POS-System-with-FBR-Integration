@php
    $route = request()->route()->getName();
    $currentBusiness = currentUserBusiness();
    $hasCurrentBusiness = !is_null($currentBusiness);
    $business_type = $currentBusiness?->business?->business_type;
    $canSaleCreate = $hasCurrentBusiness && $currentBusiness->hasPermissionTo('sale_create');
    $hasCustomerPermissions =
        $hasCurrentBusiness && $currentBusiness->hasAnyPermission(['customer_view', 'customer_create']);
    $hasSupplierPermissions =
        $hasCurrentBusiness && $currentBusiness->hasAnyPermission(['supplier_view', 'supplier_create']);
    $hasProductPermissions =
        $hasCurrentBusiness && $currentBusiness->hasAnyPermission(['product_view', 'product_create']);
    $hasUserPermissions = $hasCurrentBusiness && $currentBusiness->hasAnyPermission(['user_view', 'user_create']);
@endphp
<style>
    .current-business {
        background-color: #fd7e14 !important;
        color: #fff !important;
        flex: wrap;
    }

    .current-business:hover,
    .current-business:focus {
        background-color: #e96b02 !important;
        color: #fff !important;
    }

    /* Search box inside dropdown */
    .business-search-wrapper {
        padding: 8px 12px;
        border-bottom: 1px solid #e9ecef;
        position: sticky;
        top: 0;
        background: #fff;
        z-index: 10;
    }

    .business-search-wrapper input {
        width: 100%;
        padding: 6px 10px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 14px;
        outline: none;
    }

    .business-search-wrapper input:focus {
        border-color: #fd7e14;
        box-shadow: 0 0 0 2px rgba(253, 126, 20, 0.2);
    }

    .business-list {
        max-height: 300px;
        overflow-y: auto;
    }

    .no-results-msg {
        padding: 10px 12px;
        color: #6c757d;
        font-size: 13px;
        text-align: center;
        display: none;
    }

    @media (max-width: 767.98px) {
        .dropdown-menu {
            min-width: 260px;
            max-width: 90vw;
        }

        .business-item {
            white-space: normal !important;
            word-break: break-word;
            overflow-wrap: break-word;
        }
    }
</style>
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
    </ul>

    <ul class="navbar-nav ml-auto">
        @php
            use Illuminate\Support\Facades\DB;
            $fbrTokenDetail = DB::table('fbr_tokens')->where('business_id', session('business_id'))->first();
        @endphp

        @if ($fbrTokenDetail)
            <li class="nav-item">
                <span class="badge badge-danger d-none d-md-inline">
                    {{ $fbrTokenDetail->environment == '0' ? 'Sandbox' : 'Production' }}
                </span>
            </li>
        @endif
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">

        {{-- Business Switcher Dropdown --}}
        <li class="nav-item dropdown mr-2">
            <a class="nav-link dropdown-toggle" href="#" id="businessDropdown" role="button"
                data-toggle="dropdown">
                <i class="fas fa-store"></i>
                <span class="d-none d-md-inline">
                    {{ optional(\App\Models\Business::find(session('business_id')))->business_name ?? 'Select Business' }}
                </span>
            </a>
            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="businessDropdown">
                @php
                    $businesses = auth()->user()->hasRole('super_admin')
                        ? \App\Models\Business::all()
                        : auth()->user()->businesses;
                @endphp

                {{-- Search Filter --}}
                <div class="business-search-wrapper">
                    <input type="text" id="businessSearchInput" placeholder=" Search business..." autocomplete="off">
                </div>

                {{-- Business List --}}
                <div class="business-list" id="businessList">
                    @foreach ($businesses as $business)
                        <a href="#"
                            class="dropdown-item business-item {{ session('business_id') == $business->id ? 'current-business' : '' }}"
                            data-business-id="{{ $business->id }}" data-business-name="{{ $business->business_name }}">
                            {{ $business->business_name }}
                        </a>
                    @endforeach
                    <div class="no-results-msg" id="noResultsMsg">No business found</div>
                </div>
            </div>
        </li>

        <!-- @can('sale_create') -->
            @if ($canSaleCreate && $business_type === 'pos')
                <li class="nav-item dropdown">
                    <a class="nav-link btn pos-btn text-white" href="{{ route('backend.admin.cart.index') }}">
                        <i class="fas fa-cart-plus"></i>
                        <span class="d-none d-md-inline">POS</span>
                    </a>
                </li>
            @endif
            <!-- cart.digitalInvoice
        @endcan -->

        @if ($canSaleCreate && $business_type === 'pral')
            <li class="nav-item dropdown">
                <a class="nav-link btn pos-btn text-white" href="{{ route('backend.admin.cart.pral') }}">
                    <i class="fas fa-cart-plus"></i>
                    <span class="d-none d-md-inline">PRA Cart</span>
                </a>
            </li>
        @endif

        @if ($canSaleCreate && $business_type === 'digital')
            <li class="nav-item dropdown">
                <a class="nav-link btn pos-btn text-white" href="{{ route('backend.admin.cart.digitalInvoice') }}">
                    <i class="fas fa-cart-plus"></i>
                    <span class="d-none d-md-inline">Digital Invoice</span>
                </a>
            </li>
        @endif

        <li class="nav-item">
            <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                <i class="fas fa-expand-arrows-alt"></i>
            </a>
        </li>
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="fas fa-user-circle"></i>
                <i class="fas fa-angle-double-down"></i>
            </a>
            <div class="dropdown-menu ">
                <a href="{{ route('backend.admin.profile') }}" class="dropdown-item dropdown-footer">
                    <i class="fas fa-address-card"></i>
                    Profile
                </a>
                <div class="dropdown-divider"></div>
                <a href="{{ route('logout') }}" class="dropdown-item dropdown-footer">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </li>
    </ul>
</nav>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        // Prevent dropdown from closing when clicking inside the search input
        $('#businessSearchInput').on('click', function(e) {
            e.stopPropagation();
        });

        // Debounced live search filter
        let searchTimer;

        $('#businessSearchInput').on('keyup', function() {
            clearTimeout(searchTimer);

            const input = this;

            searchTimer = setTimeout(function() {
                const searchTerm = $(input).val().toLowerCase().trim();
                let visibleCount = 0;

                $('#businessList .business-item').each(function() {
                    const businessName = $(this).data('business-name').toString()
                        .toLowerCase();
                    if (businessName.indexOf(searchTerm) !== -1) {
                        $(this).show();
                        visibleCount++;
                    } else {
                        $(this).hide();
                    }
                });

                // Show "no results" message if nothing matches
                $('#noResultsMsg').toggle(visibleCount === 0);
            }, 150); // 150ms debounce delay
        });

        // Clear search when dropdown closes
        $('#businessDropdown').on('hidden.bs.dropdown', function() {
            clearTimeout(searchTimer);
            $('#businessSearchInput').val('');
            $('#businessList .business-item').show();
            $('#noResultsMsg').hide();
        });

        // Re-focus search input when dropdown opens
        $('#businessDropdown').on('shown.bs.dropdown', function() {
            $('#businessSearchInput').focus();
        });

        // Business switch handler
        $('.business-item').on('click', function(e) {
            e.preventDefault();

            let businessId = $(this).data('business-id');
            let businessName = $(this).data('business-name');

            let dropdownToggle = $('#businessDropdown');
            let originalText = dropdownToggle.html();
            dropdownToggle.html('<i class="fas fa-spinner fa-spin"></i>');

            $.ajax({
                url: '{{ route('business.switch') }}',
                method: 'POST',
                data: {
                    business_id: businessId,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        // Current URL update karein
                        const currentPath = window.location.pathname;
                        const newPath = currentPath.replace(/\/business\/\d+/,
                            `/business/${businessId}`);

                        // Agar URL change hui hai toh redirect karein
                        if (newPath !== currentPath) {
                            window.location.href = newPath;
                        } else {
                            window.location.reload();
                        }
                    } else {
                        dropdownToggle.html(originalText);
                    }
                },
                error: function() {
                    dropdownToggle.html(originalText);
                }
            });
        });
    });
</script>
