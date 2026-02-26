<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\HomeCarouselController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/products', [ProductController::class, 'index']); // Public catalog
Route::get('/categories-public', [App\Http\Controllers\Api\CategoryController::class, 'index']); // Public categories for filters
Route::get('/home-carousel', [HomeCarouselController::class, 'show']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    // Cart routes
    Route::get('/cart', [App\Http\Controllers\Api\CartController::class, 'index']);
    Route::post('/cart', [App\Http\Controllers\Api\CartController::class, 'store']);
    Route::put('/cart/{item}', [App\Http\Controllers\Api\CartController::class, 'update']);
    Route::delete('/cart/{item}', [App\Http\Controllers\Api\CartController::class, 'destroy']);
    Route::delete('/cart', [App\Http\Controllers\Api\CartController::class, 'clear']);

    // Order routes
    Route::get('/orders', [App\Http\Controllers\Api\OrderController::class, 'index']);
    Route::post('/orders', [App\Http\Controllers\Api\OrderController::class, 'store']);
    Route::get('/orders/{order}', [App\Http\Controllers\Api\OrderController::class, 'show']);
    
    // Admin routes
    Route::middleware(App\Http\Middleware\EnsureUserIsAdmin::class)->prefix('admin')->group(function () {
        Route::get('/notifications', [App\Http\Controllers\Api\AdminNotificationController::class, 'index']);
        Route::patch('/notifications/{notification}', [App\Http\Controllers\Api\AdminNotificationController::class, 'markAsRead']);
        Route::put('/home-carousel', [HomeCarouselController::class, 'update']);
        Route::get('/products/generate-barcode', [ProductController::class, 'generate']);
        Route::get('/products/check/{barcode}', [ProductController::class, 'check']);
        Route::apiResource('products', ProductController::class);
        Route::post('/products/{product}/stock-in', [ProductController::class, 'addStock']);

        // Categories & Subcategories
        Route::apiResource('categories', App\Http\Controllers\Api\CategoryController::class);
        Route::post('/categories/{category}/subcategories', [App\Http\Controllers\Api\CategoryController::class, 'storeSubcategory']);
        Route::put('/categories/{category}/subcategories/{subcategory}', [App\Http\Controllers\Api\CategoryController::class, 'updateSubcategory']);
        Route::delete('/categories/{category}/subcategories/{subcategory}', [App\Http\Controllers\Api\CategoryController::class, 'destroySubcategory']);

        // Attributes
        Route::apiResource('attributes', App\Http\Controllers\Api\AttributeController::class);
        Route::post('/attributes/{attribute}/values', [App\Http\Controllers\Api\AttributeController::class, 'storeValue']);
        Route::put('/attributes/{attribute}/values/{value}', [App\Http\Controllers\Api\AttributeController::class, 'updateValue']);
        Route::delete('/attributes/{attribute}/values/{value}', [App\Http\Controllers\Api\AttributeController::class, 'destroyValue']);

        Route::post('/sales/lookup', [App\Http\Controllers\Api\SaleController::class, 'lookupBarcode']);
        Route::apiResource('sales', App\Http\Controllers\Api\SaleController::class);
        Route::get('/orders', [App\Http\Controllers\Api\OrderController::class, 'indexAdmin']);
        Route::patch('/orders/{order}/status', [App\Http\Controllers\Api\OrderController::class, 'updateStatus']);
        Route::apiResource('contracts', App\Http\Controllers\Api\ContractController::class);
        Route::post('/contracts/{contract}/payments', [App\Http\Controllers\Api\ContractController::class, 'addPayment']);
        Route::post('/contracts/{contract}/extend', [App\Http\Controllers\Api\ContractController::class, 'extend']);
    });
});
