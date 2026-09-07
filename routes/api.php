<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\JoinRequestController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/join-requests', [JoinRequestController::class, 'store']);

    Route::get('/public/events/{slug}', [EventController::class, 'publicShow']);
    Route::post('/public/events/{slug}/donations', [EventController::class, 'publicDonate'])
        ->middleware('throttle:20,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/dashboard', [PaymentController::class, 'dashboard']);

        Route::get('/members', [MemberController::class, 'index']);
        Route::post('/members', [MemberController::class, 'store']);
        Route::get('/members/{member}', [MemberController::class, 'show']);
        Route::get('/members/{member}/dues', [MemberController::class, 'dues']);

        Route::get('/payments', [PaymentController::class, 'index']);
        Route::post('/payments', [PaymentController::class, 'store']);

        Route::get('/events', [EventController::class, 'index']);
        Route::post('/events', [EventController::class, 'store']);
        Route::post('/events/{event}/donations', [EventController::class, 'donate']);

        Route::get('/reports/monthly-collection', [ReportController::class, 'monthly']);

        Route::get('/join-requests', [JoinRequestController::class, 'index']);
        Route::post('/join-requests/{joinRequest}/approve', [JoinRequestController::class, 'approve']);
        Route::post('/join-requests/{joinRequest}/reject', [JoinRequestController::class, 'reject']);
    });
});
