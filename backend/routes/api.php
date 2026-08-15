<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - MyStocks v1
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1
|
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'MyStocks API',
        'version' => '1.0.0',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Auth routes will be added in Phase 3
// Business, Products, Inventory, Sales, etc. will be added in subsequent phases
