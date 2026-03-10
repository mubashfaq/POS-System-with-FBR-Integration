<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Pos\CartController;
use App\Http\Controllers\Api\Pos\OrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Route::get('/user', [AuthController::class, 'show']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/businesses', [BusinessController::class, 'index']);
    Route::post('/select-business', [BusinessController::class, 'select']);

    Route::middleware('ensure.business')->group(function () {


        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Route::get('/get/products', [CartController::class, 'getProducts'])->name('getProducts');

        // Route::get('/cart', [CartController::class, 'index'])
        //     ->middleware(['auth', 'business.type:pos'])
        //     ->name('cart.index');

        // Route::get('/digitalCart', [CartController::class, 'digitalInvoice'])
        //     ->middleware(['auth', 'business.type:digital'])
        //     ->name('cart.digitalInvoice');



        Route::post('cart', [CartController::class, 'store']);
        Route::post('cart/increment/{id}', [CartController::class, 'increment']);
        Route::post('cart/decrement/{id}', [CartController::class, 'decrement']);
        Route::post('cart/update-quantity', [CartController::class, 'updateQuantity']);
        Route::post('cart/update-price', [CartController::class, 'updatePrice']);
        Route::delete('cart/{id}', [CartController::class, 'destroy']);
        Route::delete('cart', [CartController::class, 'empty']);
        Route::post('/order/create', [OrderController::class, 'store'])
            ->middleware(['business.type:pos,both']);
        Route::post('/order/createDigital', [OrderController::class, 'storeDigital'])
            ->middleware(['business.type:digital,both']);
    });
});

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
