<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('wallets', WalletController::class)->only(['index', 'store', 'show', 'update']);
    Route::apiResource('categories', CategoryController::class)->only(['index', 'store']);
    Route::apiResource('transactions', TransactionController::class)->only(['index', 'store', 'destroy']);

    Route::get('reports/summary', [ReportController::class, 'summary'])->name('reports.summary');
});
