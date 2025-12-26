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
use App\Http\Controllers\InventoryTransactionController;

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\SellerMiddleware;
use App\Http\Middleware\ManufacturerMiddleware;
use App\Http\Middleware\SupervisorMiddleware;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'editProfile']);


    // 1. Admin Routes (Users, Products, Purchases)
    Route::middleware(AdminMiddleware::class)->prefix('admin')->group(function () {

        // User Management
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users', [UserController::class, 'index']);
        Route::put('/users/{id}/block', [UserController::class, 'block']);
        Route::put('/users/{id}/unblock', [UserController::class, 'unblock']);

        // Products
        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index']);
            Route::post('/', [ProductController::class, 'store']);
            Route::put('/{id}', [ProductController::class, 'update']);
            Route::delete('/{id}', [ProductController::class, 'destroy']);
            Route::get('/{id}/stock', [ProductController::class, 'checkStock']);
        });

        // Purchases & Suppliers
        Route::prefix('purchases')->group(function () {
            Route::post('/bill', [PurchaseController::class, 'store']);
            Route::get('/bills', [PurchaseController::class, 'index']);
        });
        Route::apiResource('suppliers', SupplierController::class);

        // General Resources
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('inventories', InventoryTransactionController::class);
    });

    // 2. Seller Routes (Sales)
    Route::middleware(SellerMiddleware::class)->prefix('sales')->group(function () {
        Route::post('/invoice', [SalesController::class, 'store']);
        Route::get('/invoices', [SalesController::class, 'index']);
        Route::get('/invoices/{id}', [SalesController::class, 'show']);
    });

    // 3. Manufacturer Routes (Manufacturing)
    Route::middleware(ManufacturerMiddleware::class)->prefix('manufacturing')->group(function () {
        Route::get('/bom', [ManufacturingController::class, 'indexBOM']);
        Route::post('/bom', [ManufacturingController::class, 'storeBOM']);
        Route::post('/execute', [ManufacturingController::class, 'executeOrder']);
        Route::get('/orders', [ManufacturingController::class, 'indexOrders']);
    });

    // 4. Supervisor Routes (Finance)
    Route::middleware(SupervisorMiddleware::class)->prefix('finance')->group(function () {
        Route::post('/pay-supplier', [FinanceController::class, 'paySupplier']);
        Route::post('/receive-payment', [FinanceController::class, 'receiveCustomerPayment']);
        Route::post('/expense', [FinanceController::class, 'storeExpense']);
        Route::get('/treasuries', [FinanceController::class, 'index']);
        Route::get('/transactions', [FinanceController::class, 'history']);
        Route::post('/treasuries', [FinanceController::class, 'storeTreasury']);
        Route::post('/expense-categories', [FinanceController::class, 'storeExpenseCategory']);
    });

});
