<?php
use App\Http\Controllers\AuthController;

use App\Http\Controllers\Admin\AuthorController;
use App\Http\Controllers\Admin\PaymentMethodeController;
use App\Http\Controllers\Admin\PaymentMethodeController as CustmerPaymentMethodeController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;

use App\Http\Controllers\Author\AuthController as AuthorAuthController;
use App\Http\Controllers\Author\BookController;
use App\Http\Controllers\Author\CategoryController as AuthorCategoryController;
use App\Http\Controllers\Author\OrderController;

use App\Http\Controllers\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Customer\BookController as CustomerBookController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CategoryController as CustomerCategoryController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AuthorMiddleware;
use App\Http\Middleware\CustomerMiddleware;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'editProfile']);
});

Route::prefix('admin')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
    Route::apiResource('category', CategoryController::class);
    Route::apiResource('author', AuthorController::class);
    Route::apiResource('user', UserController::class)->only('index');
    Route::apiResource('order', AdminOrderController::class)->only('index');
    Route::put('order/{order_id}/updateStatus', [AdminOrderController::class, 'updateSatuts']);
    Route::put('user/{user_id}/block', [UserController::class, 'block']);
    Route::put('user/{user_id}/unblock', [UserController::class, 'unblock']);
    Route::apiResource('payment', PaymentMethodeController::class);
    Route::put('author/{author}/approve', [AuthorController::class, 'approve']);
});


Route::post('customer/sign-up', [CustomerAuthController::class, 'signup']);
Route::prefix('customer')->middleware(['auth:sanctum', CustomerMiddleware::class])->group(function () {
    Route::put('editProfile', [CustomerAuthController::class, 'editProfile']);
    Route::apiResource('book', CustomerBookController::class)->only(['index', 'show']);
    Route::apiResource('order', CustomerOrderController::class)->only('index');
    Route::apiResource('category', CustomerCategoryController::class)->only('index');
    Route::apiResource('payment', CustmerPaymentMethodeController::class)->only('index');
    Route::apiResource('cart', CartController::class)->except('store');
    Route::post('cart/checkout', [CartController::class, 'checkout']);
    Route::post('cart/decrease/{book}', [CartController::class, 'decreaseQty']);
    Route::delete('cart/remove/{book}', [CartController::class, 'removeItem']);
    Route::post('cart/{book}', [CartController::class, 'store']);
});

Route::post('author/sign-up', [AuthorAuthController::class, 'signup']);
Route::prefix('author')->middleware(['auth:sanctum', AuthorMiddleware::class])->group(function () {
    Route::patch('books/updateStock/{book_id}', [BookController::class, 'updateStock']);
    Route::post('books/addRequest', [BookController::class, 'addRequest']);
    Route::post('books/rejectRequest', [BookController::class, 'rejectRequest']);
    Route::post('books/acceptRequest', [BookController::class, 'accseptRequestes']);
    Route::get('books/getRequests', [BookController::class, 'getRequestes']);
    Route::get('showAll', [BookController::class, 'showAll']);
    Route::apiResource('book', BookController::class);
    Route::apiResource('category', AuthorCategoryController::class)->only('index');
    Route::apiResource('order', OrderController::class)->only('index');

});

//-----------------------------------------------

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






