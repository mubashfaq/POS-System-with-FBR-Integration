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

        Route::controller(CartController::class)->group(function () {
            Route::post('cart', 'store');
            Route::post('cart/increment/{id}', 'increment');
            Route::post('cart/decrement/{id}', 'decrement');
            Route::post('cart/update-quantity', 'updateQuantity');
            Route::post('cart/update-price', 'updatePrice');
            Route::delete('cart/{id}', 'destroy');
            Route::delete('cart', 'empty');
        });
        Route::controller(OrderController::class)->group(function () {
            Route::get('/orders', 'index');
            Route::post('/order/create', 'store');
            Route::post('/order/createDigital', 'storeDigital')->middleware(['business.type:digital,both']);
        });

        Route::apiResource('products', \App\Http\Controllers\Api\ProductController::class);
        Route::apiResource('customers', \App\Http\Controllers\Api\CustomerController::class);
    });
});
