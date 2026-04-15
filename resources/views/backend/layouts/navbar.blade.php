@php
$route = request()->route()->getName();
$currentBusiness = currentUserBusiness();
$hasCurrentBusiness = !is_null($currentBusiness);
$business_type = $currentBusiness?->business?->business_type;
$canSaleCreate = $hasCurrentBusiness && $currentBusiness->hasPermissionTo('sale_create');
$hasCustomerPermissions = $hasCurrentBusiness && $currentBusiness->hasAnyPermission(['customer_view', 'customer_create']);
$hasSupplierPermissions = $hasCurrentBusiness && $currentBusiness->hasAnyPermission(['supplier_view', 'supplier_create']);
$hasProductPermissions = $hasCurrentBusiness && $currentBusiness->hasAnyPermission(['product_view', 'product_create']);
$hasUserPermissions = $hasCurrentBusiness && $currentBusiness->hasAnyPermission(['user_view', 'user_create']);
@endphp

<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
    </ul>

    <ul class="navbar-nav ml-auto" >
        <?php

use App\Services\FbrTokenService;
use Illuminate\Support\Facades\DB;

        $token = env('FBR_TOKEN');
        $tokens = FbrTokenService::getToken();

        if (is_array($tokens)) {
            $token = $tokens['digital_token']; 
        } else {
            $token = $tokens;
        }
        $fbrTokenDetail = DB::table('fbr_tokens')->where('business_id', session('business_id'))->first();
        if ($fbrTokenDetail->environment == '0') {
            echo '<li class="nav-item text-danger fw-bold"> Sandbox</li>';
        }else{
            echo "<li class='nav-item text-danger fw-bold'> Production</li>>";

        }
       
        ?>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">


            {{-- Business Switcher Dropdown --}}
        <li class="nav-item dropdown mr-2">
            <a class="nav-link dropdown-toggle" href="#" id="businessDropdown" role="button" data-toggle="dropdown">
                <i class="fas fa-store"></i>
                {{ optional(\App\Models\Business::find(session('business_id')))->business_name ?? 'Select Business' }}
            </a>
            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="businessDropdown">
                @php
                    $businesses = auth()->user()->hasRole('super_admin') ? \App\Models\Business::all() : auth()->user()->businesses;
                @endphp
                @foreach($businesses as $business)
                    <a href="#" 
                    class="dropdown-item business-item {{ session('business_id') == $business->id ? 'current-business' : '' }}"
                    data-business-id="{{ $business->id }}"
                    data-business-name="{{ $business->business_name }}">
                        {{ $business->business_name }}
                    </a>
                @endforeach
            </div>
        </li>




        <!-- @can('sale_create') -->
         @if ($canSaleCreate && $business_type === 'pos')
            <li class="nav-item dropdown">
                <a class="nav-link btn pos-btn text-white" href="{{ route('backend.admin.cart.index') }}">
                    <i class="fas fa-cart-plus"></i> POS
                </a>
            </li>
         @endif
         <!-- cart.digitalInvoice
        @endcan -->

          @if ($canSaleCreate && $business_type === 'digital')
            <li class="nav-item dropdown">
                <a class="nav-link btn pos-btn text-white" href="{{ route('backend.admin.cart.digitalInvoice') }}">
                    <i class="fas fa-cart-plus"></i> Digital Invoice
                </a>
            </li>
         @endif


        <!-- Notifications Dropdown Menu -->
        <!-- <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="far fa-bell"></i>
                <span class="badge badge-warning navbar-badge">15</span>
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <span class="dropdown-item dropdown-header">15 Notifications</span>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                    <i class="fas fa-envelope mr-2"></i> 4 new messages
                    <span class="float-right text-muted text-sm">3 mins</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                    <i class="fas fa-users mr-2"></i> 8 friend requests
                    <span class="float-right text-muted text-sm">12 hours</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                    <i class="fas fa-file mr-2"></i> 3 new reports
                    <span class="float-right text-muted text-sm">2 days</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item dropdown-footer">See All Notifications</a>
            </div>
        </li> -->
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
    $('.business-item').on('click', function(e) {
        e.preventDefault();
        
        let businessId = $(this).data('business-id');
        let businessName = $(this).data('business-name');
        
        let dropdownToggle = $('#businessDropdown');
        let originalText = dropdownToggle.html();
        dropdownToggle.html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.ajax({
            url: '{{ route("business.switch") }}',
            method: 'POST',
            data: {
                business_id: businessId,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    // Current URL update karein
                    const currentPath = window.location.pathname;
                    const newPath = currentPath.replace(/\/business\/\d+/, `/business/${businessId}`);
                    
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
