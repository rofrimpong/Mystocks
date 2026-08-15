<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'MyStocks API is running. Use /api/v1 endpoints.',
        'docs' => '/docs',
    ]);
});
