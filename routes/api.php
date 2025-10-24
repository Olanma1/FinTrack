<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AuthController,
    CategoryController,
    TransactionController,
    BudgetController,
    GoalController,
    WalletController,
    InsightController
};

Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/wallet', [WalletController::class, 'show']);

    Route::apiResource('transactions', TransactionController::class);

    Route::apiResource('budgets', BudgetController::class);

    Route::apiResource('goals', GoalController::class);

    Route::get('/insights/summary', [InsightController::class, 'summary']);
});