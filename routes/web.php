<?php

use App\Http\Controllers\Backend\CurrencyController;
use App\Http\Controllers\Backend\Pos\CartController;
use App\Http\Controllers\Backend\Product\ProductController;
use App\Http\Controllers\Backend\Report\ReportController;
use App\Http\Controllers\Backend\SupplierController;
use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Backend\BusinessController;
use App\Http\Controllers\Backend\Product\CategoryController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\RolePermission\PermissionController;
use App\Http\Controllers\Backend\Pos\OrderController;
use App\Http\Controllers\Backend\Product\BrandController;
use App\Http\Controllers\Backend\Product\PurchaseController;
use App\Http\Controllers\Backend\RolePermission\RoleController;
use App\Http\Controllers\Backend\Product\UnitController;
use App\Http\Controllers\Backend\UserManagementController;
use App\Http\Controllers\Backend\WebsiteSettingController;
use App\Models\Supplier;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// ====================== FRONTEND ======================

// homepage
Route::get('/', function () {
    return to_route('login');
})->name('frontend.home');

//authentication
Route::match(['get', 'post'], 'login', [AuthController::class, 'login'])->name('login');

// login time business create or select

Route::get('/business-selection', [BusinessController::class, 'selection'])->name('business.selection');
Route::post('/create-business', [BusinessController::class, 'create'])->name('business.create');
Route::post('/select-business', [BusinessController::class, 'select'])->name('business.select');

Route::post('/switch-business', [BusinessController::class, 'switchBusiness'])->name('business.switch');

Route::get('logout', [AuthController::class, 'logout'])->name('logout');
Route::match(['post', 'get'], 'sign-up', [AuthController::class, 'register'])->name('signup');
// Route::match(['post', 'get'], 'sign-up', [AuthController::class, 'login'])->name('signup');
Route::match(['get', 'post'], 'forget-password', [AuthController::class, 'forgetPassword'])->name('forget.password');
Route::match(['get', 'post'], 'new-password', [AuthController::class, 'newPassword'])->name('new.password');
Route::match(['get', 'post'], 'password-reset', [AuthController::class, 'resetPassword'])->name('password.reset');
Route::get('resend-otp', [AuthController::class, 'resendOtp'])->name('resend.otp');

// google auth
Route::get('auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.handle.callback');

// ====================== /FRONTEND =====================

// ====================== BACKEND =======================
Route::post('/admin/set-customer-session', function(Request $request) {
    session(['customer_is_registered' => $request->is_registered]);
    return response()->json(['success' => true]);
})->middleware(['admin']);


Route::prefix('admin')->as('backend.admin.')->middleware(['admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('products', ProductController::class);
    Route::resource('brands', BrandController::class);
    // Route::resource('business', BusinessController::class);
    Route::resource('business', BusinessController::class)
    ->middleware('current.business');


    Route::resource('orders', OrderController::class);
    Route::resource('purchase', PurchaseController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('customers', CustomerController::class);
    Route::resource('products', ProductController::class);
    Route::get('products/hsCode-desc/{hs_code}', [ProductController::class, 'getDescription'])->name('products.hsCode-desc');
    Route::get('products/sale-type/{sale_type}', [ProductController::class, 'getSaleTypeId'])->name('products.sale-type');
    Route::get('products/sro-schedule/{rate_tax_id}', [ProductController::class, 'getSroSchedule'])->name('products.sro-schedule');
    Route::get('products/sro-Items-details/{sro_schedule_id}', [ProductController::class, 'getSroItemDetail'])->name('products.sro-Items-details');

    
    Route::resource('units', UnitController::class);
    Route::resource('currencies', CurrencyController::class);
    Route::match(['get', 'post'], 'import/products', [ProductController::class,'import'])->name('products.import');
    Route::get('currencies/default/{id}', [CurrencyController::class, 'setDefault'])->name('currencies.setDefault');
    Route::get('customers/orders/{id}', [CustomerController::class, 'orders'])->name('customers.orders');
    Route::post('select-customer/{id}', [CustomerController::class, 'selectCustomer'])->name('select-customer');
    Route::get('purchase/products/{id}', [PurchaseController::class, 'purchaseProducts'])->name('purchase.products');
    Route::get('orders/invoice/{id}', [OrderController::class,'invoice'])->name('orders.invoice');
    Route::get('orders/pos-invoice/{id}', [OrderController::class, 'posInvoice'])->name('orders.pos-invoice');
    Route::get('orders/transactions/{id}', [OrderController::class, 'transactions'])->name('orders.transactions');
    Route::match(['get', 'post'], 'orders/due/collection/{id}', [OrderController::class, 'collection'])->name('due.collection');
    Route::get('collection/invoice/{id}', [OrderController::class, 'collectionInvoice'])->name('collectionInvoice');
    Route::resource('categories', CategoryController::class);
    //start report

    Route::get('/sale/summery', [ReportController::class, 'saleSummery'])->name('sale.summery');
    Route::get('/sale/report', [ReportController::class, 'saleReport'])->name('sale.report');
    Route::get('/inventory/report', [ReportController::class, 'inventoryReport'])->name('inventory.report');
    //end report
    
    // start pos
        Route::get('/get/products', [CartController::class, 'getProducts'])->name('getProducts');
        Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
        Route::get('/digitalCart', [CartController::class, 'digitalInvoice'])->name('cart.digitalInvoice');
        Route::post('/cart', [CartController::class, 'store'])->name('cart.store');

        Route::put('/cart/update-quantity', [CartController::class, 'updateQuantity']);
        Route::put('/cart/update-price', [CartController::class, 'updatePrice']);


        Route::put('/cart/increment', [CartController::class, 'increment']);
        Route::put('/cart/decrement', [CartController::class, 'decrement']);
        Route::put('/cart/delete', [CartController::class, 'delete']);
        Route::put('/cart/empty', [CartController::class, 'empty']);
        Route::put('/order/create', [OrderController::class, 'store']);
        Route::put('/order/createDigital', [OrderController::class, 'storeDigital']);
        Route::get('orders/fbr-status/{id}', [OrderController::class, 'fbrStatus'])->name('orders.fbr-status');

        Route::get('/get/customers',[CustomerController::class,'getCustomers']);
        Route::post('/create/customers', [CustomerController::class, 'store']);
    //end pos
    Route::get('profile', [DashboardController::class, 'profile'])->name('profile');
    Route::post('profile/update', [AuthController::class, 'update'])->name('profile.update');

    // user management
    Route::prefix('users')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('users');
        Route::get('suspend/{id}/{status}', [UserManagementController::class, 'suspend'])->name('user.suspend');
        Route::match(['get', 'post'], 'create', [UserManagementController::class, 'create'])->name('user.create');
        Route::match(['get', 'post'], 'edit/{id}', [UserManagementController::class, 'edit'])->name('user.edit');
        Route::get('delete/{id}', [UserManagementController::class, 'delete'])->name('user.delete');
    });

    // settings
    Route::prefix('settings')->group(function () {
        // website settings
        Route::prefix('website')->group(function () {
            Route::controller(WebsiteSettingController::class)->prefix('general')->group(function () {
                Route::get('/', 'websiteGeneral')->name('settings.website.general');
                Route::post('update-info', 'websiteInfoUpdate')->name('settings.website.info.update');
                Route::post('update-contacts', 'websiteContactsUpdate')->name('settings.website.contacts.update');
                Route::post('update-social-links', 'websiteSocialLinkUpdate')->name('settings.website.social.link.update');
                Route::post('update-style-settings', 'websiteStyleSettingsUpdate')->name('settings.website.style.settings.update');
                Route::post('update-custom-css', 'websiteCustomCssUpdate')->name('settings.website.custom.css.update');
                Route::post('update-notification-settings', 'websiteNotificationSettingsUpdate')->name('settings.website.notification.settings.update');
                Route::post('update-website-status', 'websiteStatusUpdate')->name('settings.website.status.update');
                Route::post('update-invoice-settings', 'websiteInvoiceUpdate')->name('settings.website.invoice.update');

                Route::post('/website/invoice/update', [WebsiteSettingController::class, 'websiteInvoiceUpdate'])
                ->name('backend.admin.settings.website.invoice.update');
            });

            Route::controller(RoleController::class)->prefix('roles')->group(function () {
                Route::get('/', 'index')->name('roles');
                Route::post('create', 'store')->name('roles.create');
                Route::get('show/{id}', 'show')->name('roles.show');
                Route::put('update/{id}', 'update')->name('roles.update');
                Route::get('delete/{id}', 'destroy')->name('roles.delete');
                Route::post('role-permission/{id}', 'updatePermission')->name('update.role-permissions');
                Route::get('role-wise-permissions/{id?}', 'roleWisePermissions')->name('role-wise-permissions');
            });

            Route::controller(PermissionController::class)->prefix('permissions')->group(function () {
                Route::get('/', 'index')->name('permissions');
                Route::post('create', 'store')->name('permissions.store');
                // Route::get('show/{id}', 'show')->name('roles.show');
                Route::put('update/{id}', 'update')->name('permissions.update');
                Route::get('delete/{id}', 'destroy')->name('permissions.delete');
            });
        });
    });
});

// ====================== /BACKEND ======================

Route::get('clear-all', function () {
    Artisan::call('optimize:clear');
    return redirect()->back();
});

Route::get('storage-link', function () {
    Artisan::call('storage:link');
    return redirect()->back();
});

Route::get('test', [TestController::class, 'test'])->name('test');
