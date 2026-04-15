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

<div class="sidebar">
    <!-- Sidebar user panel (optional) -->
    <!-- <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
            <img src="{{ auth()->user()->pro_pic }}" class="img-circle elevation-2" style="width: 2.5rem; height: 2.5rem;"
                alt="User Image">
        </div>
        <div class="info">
            <a href="{{ route('backend.admin.profile') }}" class="d-block">
                {{ auth()->user()->name }}
            </a>
        </div>
    </div> -->

    <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

            <li class="nav-item">
                <a href="{{ route('backend.admin.dashboard') }}"
                    class="nav-link {{ $route === 'backend.admin.dashboard' ? 'active' : '' }}">
                    <i class="nav-icon fas fa-tachometer-alt"></i>
                    <p>
                        Dashboard
                    </p>
                </a>
            </li>

            @if ($canSaleCreate)

                @if ($business_type === 'pos' || $business_type === 'both')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.cart.index') }}"
                            class="nav-link {{ $route === 'backend.admin.cart.index' ? 'active' : '' }}">
                            <i class="nav-icon fas fa-cart-plus"></i>
                            <p>POS</p>
                        </a>
                    </li>
                @endif

                @if ($business_type === 'digital' || $business_type === 'both')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.cart.digitalInvoice') }}"
                            class="nav-link {{ $route === 'backend.admin.cart.digitalInvoice' ? 'active' : '' }}">
                            <i class="nav-icon fas fa-cart-plus"></i>
                            <p>Digital Invoice</p>
                        </a>
                    </li>
                @endif

            @endif
            {{-- Business Details --}}
            @if (currentUserBusiness() && currentUserBusiness()->hasAnyPermission(['business_update']))
                <li class="nav-item {{ request()->routeIs(['backend.admin.business.index','backend.admin.business.create','backend.admin.business.edit']) ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.business.index','backend.admin.business.create','backend.admin.business.edit']) ? 'active' : '' }}">
                        <i class="fas fa-briefcase nav-icon"></i>
                        <p>
                            Business Info
                            <i class="fas fa-angle-left right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('backend.admin.business.edit', session('business_id')) }}"
                                class="nav-link {{ request()->routeIs(['backend.admin.business.edit']) ? 'active' : '' }}">
                                <i class="fas fa-circle nav-icon"></i>
                                <p>Business</p>
                            </a>
                        </li>
                    </ul>
                </li>
            @endif
            {{-- @endcan --}}
            @if (currentUserBusiness() && currentUserBusiness()->hasAnyPermission([
            //customer
            'customer_create',
            'customer_view',
            'customer_update',
            'customer_delete',
            'customer_sales',
            //supplier
            'supplier_create',
            'supplier_view',
            'supplier_update',
            'supplier_delete',
            ]))
            <li class="nav-item {{ request()->routeIs(['backend.admin.customers.index', 'backend.admin.customers.create', 'backend.admin.customers.edit','backend.admin.suppliers.index', 'backend.admin.suppliers.create', 'backend.admin.suppliers.edit']) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link">
                    <i class="fas fa-user-circle nav-icon"></i>
                    <p>
                        Customer
                        <i class="fas fa-angle-left right"></i>
                    </p>
                </a>
                <ul class="nav nav-treeview">
                    @if ($currentBusiness->hasAnyPermission(['customer_create','customer_view','customer_update','customer_delete']))
                    <li class="nav-item">
                        <a href="{{route('backend.admin.customers.index')}}"
                            class="nav-link {{ request()->routeIs(['backend.admin.customers.index','backend.admin.customers.edit','backend.admin.customers.create']) ? 'active' : '' }}">
                            <i class="fas fa-circle nav-icon"></i>
                            <p>Customer</p>
                        </a>
                    </li>
                    @endif
                </ul>
                <ul class="nav nav-treeview">
                    @if ($currentBusiness->hasAnyPermission(['supplier_create','supplier_view','supplier_update','supplier_delete']))
                    <li class="nav-item">
                        <a href="{{route('backend.admin.suppliers.index')}}"
                            class="nav-link {{ request()->routeIs(['backend.admin.suppliers.index','backend.admin.suppliers.edit','backend.admin.suppliers.create']) ? 'active' : '' }}">
                            <i class="fas fa-circle nav-icon"></i>
                            <p>Supplier</p>
                        </a>
                    </li>
                    @endif
                </ul>
            </li>
            @endif
            @if ($currentBusiness->hasAnyPermission([
            'product_create',
            'product_view',
            'product_update',
            'product_delete',
            'product_import',
            'product_purchase',
            ]))
            <li class="nav-item {{ request()->routeIs(['backend.admin.products.index', 'backend.admin.products.create', 'backend.admin.products.edit', 'backend.admin.brands.index', 'backend.admin.brands.create', 'backend.admin.brands.edit', 'backend.admin.categories.index', 'backend.admin.categories.create', 'backend.admin.categories.edit']) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.products.index', 'backend.admin.products.create', 'backend.admin.products.edit', 'backend.admin.brands.index', 'backend.admin.brands.create', 'backend.admin.brands.edit', 'backend.admin.categories.index', 'backend.admin.categories.create', 'backend.admin.categories.edit']) ? 'active' : '' }}">

                    <i class="fas fa-box nav-icon"></i>
                    <p>
                        Product
                        <i class="fas fa-angle-left right"></i>
                    </p>
                </a>
                <ul class="nav nav-treeview">
                    @if ($currentBusiness->hasAnyPermission(['product_view','product_update','product_delete']))
                    <li class="nav-item">
                        <a href="{{route('backend.admin.products.index')}}"
                            class="nav-link {{ request()->routeIs(['backend.admin.products.index', 'backend.admin.products.edit']) ? 'active' : '' }}">
                            <i class="fas fa-circle nav-icon"></i>
                            <p>Product List</p>
                        </a>
                    </li>
                    @endif
                    <li class="nav-item">
                        <a href="{{route('backend.admin.products.create')}}"
                            class="nav-link {{ request()->routeIs(['backend.admin.products.create']) ? 'active' : '' }}">
                            <i class="fas fa-circle nav-icon"></i>
                            <p>Product Create</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="{{route('backend.admin.products.import')}}"
                            class="nav-link {{ request()->routeIs(['backend.admin.products.import']) ? 'active' : '' }}">
                            <i class="fas fa-circle nav-icon"></i>
                            <p>Product Import</p>
                        </a>
                    </li>

                    @if ($currentBusiness->hasAnyPermission(['brand_create','brand_view','brand_update','brand_delete']))
                    <li class="nav-item">
                        <a href="{{route('backend.admin.brands.index')}}"
                            class="nav-link {{ request()->routeIs(['backend.admin.brands.index', 'backend.admin.brands.create', 'backend.admin.brands.edit']) ? 'active' : '' }}">
                            <i class="fas fa-circle nav-icon"></i>
                            <p>Brand</p>
                        </a>
                    </li>@endif
                    @if ($currentBusiness->hasAnyPermission(['category_create','category_view','category_update','category_delete']))
                    <li class="nav-item">
                        <a href="{{route('backend.admin.categories.index')}}"
                            class="nav-link {{ request()->routeIs([ 'backend.admin.categories.index', 'backend.admin.categories.create', 'backend.admin.categories.edit']) ? 'active' : '' }}">
                            <i class="fas fa-circle nav-icon"></i>
                            <p>Category</p>
                        </a>
                    </li>@endif
                    @if ($currentBusiness->hasAnyPermission(['unit_create','unit_view','unit_update','unit_delete']))
                    <li class="nav-item">
                        <a href="{{route('backend.admin.units.index')}}"
                            class="nav-link {{ request()->routeIs([ 'backend.admin.units.index', 'backend.admin.units.create', 'backend.admin.units.edit']) ? 'active' : '' }}">
                            <i class="fas fa-circle nav-icon"></i>
                            <p>Unit</p>
                        </a>
                    </li>
                    @endif
                </ul>
            </li>
            @endif

            @if ($currentBusiness->hasAnyPermission([
            'sale_create',
            'sale_view',
            'sale_update',
            'sale_delete',
            ]))
            <li class="nav-item">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.orders.index', 'backend.admin.orders.create', 'backend.admin.orders.edit']) ? 'menu-open' : '' }}">
                    <i class="fas fa-tags nav-icon"></i>
                    <p>
                        Sale
                        <i class="fas fa-angle-left right"></i>
                    </p>
                </a>
                <ul class="nav nav-treeview">
                    <li class="nav-item">
                        <a href="{{route('backend.admin.orders.index')}}"
                            class="nav-link {{ request()->routeIs(['backend.admin.orders.index']) ? 'active' : '' }}">
                            <i class="fas fa-circle nav-icon"></i>
                            <p>Sale List</p>
                        </a>
                    </li>
                </ul>
            </li>
            @endif
            @if ($currentBusiness->hasAnyPermission([
            'purchase_create',
            'purchase_view',
            'purchase_update',
            'purchase_delete',
            ]))
            <li class="nav-item">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.purchase.index', 'backend.admin.purchase.create', 'backend.admin.purchase.edit']) ? 'menu-open' : '' }}">
                    <i class="fas fa-shopping-bag nav-icon"></i>
                    <p>
                        Purchase
                        <i class="fas fa-angle-left right"></i>
                    </p>
                </a>
                <ul class="nav nav-treeview">

                    <li class="nav-item">
                        <a href="{{route('backend.admin.purchase.index')}}"
                            class="nav-link {{ request()->routeIs(['backend.admin.purchase.index']) ? 'active' : '' }}">
                            <i class="fas fa-circle nav-icon"></i>
                            <p>Purchase List</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="{{route('backend.admin.purchase.create')}}"
                            class="nav-link {{ request()->routeIs(['backend.admin.purchase.create']) ? 'active' : '' }}">
                            <i class="fas fa-circle nav-icon"></i>
                            <p>Purchase Create</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="{{route('backend.admin.purchase.import')}}"
                            class="nav-link {{ request()->routeIs(['backend.admin.purchase.import']) ? 'active' : '' }}">
                            <i class="fas fa-circle nav-icon"></i>
                            <p>Purchase Import</p>
                        </a>
                    </li>

                </ul>
            </li>
            @endif
            @if ($currentBusiness->hasAnyPermission([
            'reports_summary',
            'reports_sales',
            'reports_inventory',
            ]))
            <li class="nav-item">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.sale.report','backend.admin.sale.summery']) ? 'menu-open' : '' }}">
                    <i class="fas fa-chart-bar nav-icon"></i>
                    <p>
                        Reports
                        <i class="fas fa-angle-left right"></i>
                    </p>
                </a>
                <ul class="nav nav-treeview">
                    <li class="nav-item">
                        <a href="{{route('backend.admin.sale.summery')}}"
                            class="nav-link {{ request()->routeIs(['backend.admin.sale.summery']) ? 'active' : '' }}">
                            <i class="fas fa-circle nav-icon"></i>
                            <p>Sales Summary</p>
                        </a>
                    </li>


                    <li class="nav-item">
                        <a href="{{route('backend.admin.sale.report')}}"
                            class="nav-link {{ request()->routeIs(['backend.admin.sale.report']) ? 'active' : '' }}">
                            <i class="fas fa-circle nav-icon"></i>
                            <p>Sales</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="{{route('backend.admin.inventory.report')}}"
                            class="nav-link {{ request()->routeIs(['backend.admin.inventory.report']) ? 'active' : '' }}">
                            <i class="fas fa-circle nav-icon"></i>
                            <p>Inventory</p>
                        </a>
                    </li>
                </ul>
            </li>
            @endif
            {{-- settings --}}
            @if ($currentBusiness->hasAnyPermission([
            //currency
            'currency_create',
            'currency_view',
            'currency_update',
            'currency_delete',
            'currency_set_default',
            //role
            'role_create',
            'role_view',
            'role_update',
            'role_delete',
            'permission_view',
            //user
            'user_create',
            'user_view',
            'user_update',
            'user_delete',
            'user_suspend',
            //setting
            'website_settings',
            'contact_settings',
            'socials_settings',
            'style_settings',
            'custom_settings',
            'notification_settings',
            'website_status_settings',
            'invoice_settings',
            ]))
            <li class="nav-header">SETTINGS</li>

            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="fas fa-cog nav-icon"></i>
                    <p>
                        Website Settings
                        <i class="fas fa-angle-left right"></i>
                    </p>
                </a>
                <ul class="nav nav-treeview">
                    @hasrole('super_admin')
                        @if ($currentBusiness->hasAnyPermission([
                        'website_settings',
                        'contact_settings',
                        'socials_settings',
                        'style_settings',
                        'custom_settings',
                        'notification_settings',
                        'website_status_settings',
                        'invoice_settings',
                        ]))
                            <li class="nav-item">
                                <a href="{{ route('backend.admin.settings.website.general') }}?active-tab=website-info"
                                    class="nav-link {{ $route === 'backend.admin.settings.website.general' ? 'active' : '' }}">
                                    <i class="fas fa-circle nav-icon"></i>
                                    <p>General Settings</p>
                                </a>
                            </li>
                        @endif 
                        @if ($currentBusiness->hasAnyPermission(['currency_create','currency_view','currency_update','currency_delete']))
                            <li class="nav-item">
                                <a href="{{ route('backend.admin.currencies.index') }}"
                                    class="nav-link {{ request()->routeIs([ 'backend.admin.currencies.index', 'backend.admin.currencies.create', 'backend.admin.currencies.edit']) ? 'active' : '' }}">
                                    <i class="fas fa-coins nav-icon"></i>
                                    <p>Currency</p>
                                </a>
                            </li>
                        @endif 
                    @endhasrole
                    @if ($currentBusiness->hasAnyPermission([
                    'role_create',
                    'role_view',
                    'role_update',
                    'role_delete',
                    'permission_view',
                    ]))
                    <li class="nav-item">
                        <a href="#" class="nav-link d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-chevron-circle-right nav-icon"></i>
                                Roles & Permissions
                            </span>
                            <span class="d-flex justify-content-between align-items-center">
                                <i class="fas fa-angle-left right"></i>
                            </span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('backend.admin.roles') }}"
                                    class="nav-link {{ $route === 'backend.admin.roles' ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Roles</p>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="{{ route('backend.admin.permissions') }}"
                                    class="nav-link {{ $route === 'backend.admin.permissions' ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Permissions</p>
                                </a>
                            </li>

                        </ul>
                    </li>
                    @endif
                    
                @if ($currentBusiness && $currentBusiness->hasAnyPermission([
                    'user_create',
                    'user_view',
                    'user_update',
                    'user_delete',
                    'user_suspend',
                    ]))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.users') }}"
                            class="nav-link {{ $route === 'backend.admin.users' ? 'active' : '' }}">
                            <i class="fas fa-circle nav-icon"></i>
                            <p>User Management</p>
                        </a>
                    </li>
                    @endif
                </ul>
            </li>
            @endif
        </ul>
    </nav>

</div>

<script>
    const treeviewElements = document.querySelectorAll('.nav-treeview');
    // Iterate over each treeview element
    treeviewElements.forEach(treeviewElement => {

        const navLinkElements = treeviewElement.querySelectorAll('.nav-link.active');

        if (navLinkElements.length > 0) {
            const parentNavItem = treeviewElement.closest('.nav-item');
            if (parentNavItem) {
                parentNavItem.classList.add('menu-open');
            }
        
            const childNavLink = parentNavItem.querySelector('.nav-link');
            if (childNavLink) {
                childNavLink.classList.add('active');
            }
        }
    });
</script>