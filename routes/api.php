<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ManufacturingController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CategoryController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'editProfile']);
});

Route::prefix('admin')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {

    // --- 1. Products ---
    Route::prefix('products')->group(function () {
        // List all products (supports ?type=raw_material filter)
        Route::get('/', [ProductController::class, 'index']);

        // Create new product (Raw material, Porcelain, or Manufactured)
        Route::post('/', [ProductController::class, 'store']);

        // Update/Delete specific product
        Route::put('/{id}', [ProductController::class, 'update']);

        Route::delete('/{id}', [ProductController::class, 'destroy']);

        // Quick stock check for UI
        Route::get('/{id}/stock', [ProductController::class, 'checkStock']);
    });

    // Category Management (Standard CRUD)
    Route::apiResource('categories', CategoryController::class);

    // --- 2. Sales & Customers ---
    Route::prefix('sales')->group(function () {
        // Create a new Sales Invoice (The main logic)
        Route::post('/invoice', [SalesController::class, 'store']);

        // Get Invoice History
        Route::get('/invoices', [SalesController::class, 'index']);
        Route::get('/invoices/{id}', [SalesController::class, 'show']);
    });

    // Customer Management (Standard CRUD)
    Route::apiResource('customers', CustomerController::class);


    // --- 3. Purchasing & Suppliers ---
    Route::prefix('purchases')->group(function () {
        // Create a new Purchase Bill (Restocking)
        Route::post('/bill', [PurchaseController::class, 'store']);

        // Get Purchase History
        Route::get('/bills', [PurchaseController::class, 'index']);
    });

    // Supplier Management (Standard CRUD)
    Route::apiResource('suppliers', SupplierController::class);


    // --- 4. Manufacturing System ---
    Route::prefix('manufacturing')->group(function () {
        // Bill of Materials (Recipes)
        Route::get('/bom', [ManufacturingController::class, 'indexBOM']);
        Route::post('/bom', [ManufacturingController::class, 'storeBOM']);

        // Production Orders
        Route::post('/execute', [ManufacturingController::class, 'executeOrder']); // Run production logic
        Route::get('/orders', [ManufacturingController::class, 'indexOrders']);
    });


    // --- 5. Finance & Treasury ---
    Route::prefix('finance')->group(function () {
        // Transaction Actions
        Route::post('/pay-supplier', [FinanceController::class, 'paySupplier']);
        Route::post('/receive-payment', [FinanceController::class, 'receiveCustomerPayment']);
        Route::post('/expense', [FinanceController::class, 'storeExpense']);

        // View Treasury Balances
        Route::get('/treasuries', [FinanceController::class, 'index']);

        // View Transaction History
        Route::get('/transactions', [FinanceController::class, 'history']);
    });

    // --- 6. Categories ---
    Route::apiResource('categories', CategoryController::class);
});
