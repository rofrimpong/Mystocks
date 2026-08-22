<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BusinessController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\ExpenseCategoryController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReferralController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'CNMG STOCKS API',
        'version' => '1.0.0',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::get('/referrals/summary', [ReferralController::class, 'summary']);

    Route::prefix('admin')->group(function () {
        Route::get('/summary', [AdminController::class, 'summary']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::patch('/users/{id}', [AdminController::class, 'updateUser']);
        Route::get('/businesses', [AdminController::class, 'businesses']);
        Route::patch('/businesses/{id}', [AdminController::class, 'updateBusiness']);
        Route::get('/plans', [AdminController::class, 'plans']);
        Route::patch('/plans/{id}', [AdminController::class, 'updatePlan']);
    });
    Route::get('/plans', [BusinessController::class, 'plans']);
    Route::get('/businesses', [BusinessController::class, 'index']);
    Route::get('/businesses/{id}', [BusinessController::class, 'show']);
    Route::put('/businesses/{id}', [BusinessController::class, 'update']);

    Route::middleware('business')->group(function () {
        Route::post('/billing/initialize', [BillingController::class, 'initialize']);
        Route::post('/billing/verify', [BillingController::class, 'verify']);
        Route::get('/business/current', [BusinessController::class, 'current']);

        Route::get('/branches', [BranchController::class, 'index']);
        Route::post('/branches', [BranchController::class, 'store'])->middleware('business.role:manager');
        Route::get('/branches/{id}', [BranchController::class, 'show']);
        Route::put('/branches/{id}', [BranchController::class, 'update'])->middleware('business.role:manager');
        Route::delete('/branches/{id}', [BranchController::class, 'destroy'])->middleware('business.role:manager');

        Route::get('/staff', [StaffController::class, 'index'])->middleware('business.role:manager');
        Route::post('/staff', [StaffController::class, 'store'])->middleware('business.role:manager');
        Route::put('/staff/{id}', [StaffController::class, 'update'])->middleware('business.role:manager');

        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store'])->middleware('business.role:manager,inventory_officer');
        Route::get('/categories/{id}', [CategoryController::class, 'show']);
        Route::put('/categories/{id}', [CategoryController::class, 'update'])->middleware('business.role:manager,inventory_officer');
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->middleware('business.role:manager,inventory_officer');

        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store'])->middleware('business.role:manager,inventory_officer');
        Route::get('/products/{id}', [ProductController::class, 'show']);
        Route::put('/products/{id}', [ProductController::class, 'update'])->middleware('business.role:manager,inventory_officer');
        Route::delete('/products/{id}', [ProductController::class, 'destroy'])->middleware('business.role:manager,inventory_officer');
        Route::post('/products/{id}/image', [ProductController::class, 'uploadImage'])->middleware('business.role:manager,inventory_officer');

        Route::get('/inventory/balances', [InventoryController::class, 'balances']);
        Route::get('/inventory/balances/{productId}', [InventoryController::class, 'showBalance']);
        Route::get('/inventory/movements', [InventoryController::class, 'movements']);
        Route::post('/inventory/opening-stock', [InventoryController::class, 'openingStock'])->middleware('business.role:manager,inventory_officer');
        Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])->middleware('business.role:manager,inventory_officer');

        Route::get('/purchases', [PurchaseController::class, 'index'])->middleware('business.role:manager,inventory_officer');
        Route::post('/purchases', [PurchaseController::class, 'store'])->middleware('business.role:manager,inventory_officer');
        Route::get('/purchases/{id}', [PurchaseController::class, 'show'])->middleware('business.role:manager,inventory_officer');

        Route::get('/sales', [SaleController::class, 'index'])->middleware('business.role:manager,cashier,salesperson');
        Route::post('/sales', [SaleController::class, 'store'])->middleware('business.role:manager,cashier,salesperson');
        Route::get('/sales/{id}', [SaleController::class, 'show'])->middleware('business.role:manager,cashier,salesperson');
        Route::post('/sales/{id}/cancel', [SaleController::class, 'cancel'])->middleware('business.role:manager');

        Route::get('/customers', [CustomerController::class, 'index'])->middleware('business.role:manager,cashier,salesperson');
        Route::post('/customers', [CustomerController::class, 'store'])->middleware('business.role:manager,cashier,salesperson');
        Route::get('/customers/{id}', [CustomerController::class, 'show'])->middleware('business.role:manager,cashier,salesperson');
        Route::put('/customers/{id}', [CustomerController::class, 'update'])->middleware('business.role:manager,cashier,salesperson');
        Route::get('/customers/{id}/transactions', [CustomerController::class, 'transactions'])->middleware('business.role:manager,cashier,salesperson');
        Route::post('/customers/{id}/payments', [CustomerController::class, 'payment'])->middleware('business.role:manager,cashier');
        Route::post('/customers/{id}/opening-balance', [CustomerController::class, 'openingBalance'])->middleware('business.role:manager');
        Route::post('/customers/{id}/adjustments', [CustomerController::class, 'adjustment'])->middleware('business.role:manager');
        Route::delete('/customers/{id}', [CustomerController::class, 'destroy'])->middleware('business.role:manager');

        Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('business.role:manager,inventory_officer');
        Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('business.role:manager,inventory_officer');
        Route::get('/suppliers/{id}', [SupplierController::class, 'show'])->middleware('business.role:manager,inventory_officer');
        Route::put('/suppliers/{id}', [SupplierController::class, 'update'])->middleware('business.role:manager,inventory_officer');
        Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy'])->middleware('business.role:manager');

        // Expenses
        Route::get('/expense-categories', [ExpenseCategoryController::class, 'index'])->middleware('business.role:manager');
        Route::post('/expense-categories', [ExpenseCategoryController::class, 'store'])->middleware('business.role:manager');
        Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('business.role:manager');
        Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('business.role:manager');
        Route::get('/expenses/{id}', [ExpenseController::class, 'show'])->middleware('business.role:manager');
        Route::put('/expenses/{id}', [ExpenseController::class, 'update'])->middleware('business.role:manager');
        Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy'])->middleware('business.role:manager');

        // Reports / Profit
        Route::get('/reports/profit', [ReportController::class, 'profitSummary'])->middleware('business.role:manager');
        Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);
        Route::get('/reports/best-sellers', [ReportController::class, 'bestSellers'])->middleware('business.role:manager');
        Route::get('/reports/low-stock', [ReportController::class, 'lowStock'])->middleware('business.role:manager,inventory_officer');
        Route::get('/reports/inventory-valuation', [ReportController::class, 'inventoryValuation'])->middleware('business.role:manager,inventory_officer');
        Route::get('/reports/sales-by-day', [ReportController::class, 'salesByDay'])->middleware('business.role:manager');

        // Offline Sync
        Route::post('/sync/push', [SyncController::class, 'push']);
        Route::get('/sync/status', [SyncController::class, 'status']);
    });
});
