<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommitteeMemberController;
use App\Http\Controllers\Api\CommitteeRoleController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ExpenseHeadController;
use App\Http\Controllers\Api\JoinRequestController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\OrganizationSettingController;
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

        Route::get('/expenses', [ExpenseController::class, 'index']);
        Route::get('/expenses/salary-dues', [ExpenseController::class, 'salaryDues']);
        Route::post('/expenses', [ExpenseController::class, 'store']);

        Route::get('/expense-heads', [ExpenseHeadController::class, 'index']);
        Route::post('/expense-heads', [ExpenseHeadController::class, 'store']);
        Route::put('/expense-heads/{expenseHead}', [ExpenseHeadController::class, 'update']);
        Route::delete('/expense-heads/{expenseHead}', [ExpenseHeadController::class, 'destroy']);

        Route::get('/members', [MemberController::class, 'index']);
        Route::post('/members', [MemberController::class, 'store']);
        Route::get('/members/{member}', [MemberController::class, 'show']);
        Route::get('/members/{member}/dues', [MemberController::class, 'dues']);

        Route::get('/payments', [PaymentController::class, 'index']);
        Route::post('/payments', [PaymentController::class, 'store']);
        Route::post('/payments/{payment}/approve', [PaymentController::class, 'approve']);
        Route::post('/payments/{payment}/reject', [PaymentController::class, 'reject']);

        Route::get('/events', [EventController::class, 'index']);
        Route::post('/events', [EventController::class, 'store']);
        Route::post('/events/{event}/donations', [EventController::class, 'donate']);

        Route::get('/reports/monthly-collection', [ReportController::class, 'monthly']);

        Route::get('/join-requests', [JoinRequestController::class, 'index']);
        Route::post('/join-requests/{joinRequest}/approve', [JoinRequestController::class, 'approve']);
        Route::post('/join-requests/{joinRequest}/reject', [JoinRequestController::class, 'reject']);

        Route::get('/organization-settings', [OrganizationSettingController::class, 'show']);
        Route::put('/organization-settings', [OrganizationSettingController::class, 'update']);

        Route::get('/committee-roles', [CommitteeRoleController::class, 'index']);
        Route::post('/committee-roles', [CommitteeRoleController::class, 'store']);
        Route::put('/committee-roles/{committeeRole}', [CommitteeRoleController::class, 'update']);
        Route::delete('/committee-roles/{committeeRole}', [CommitteeRoleController::class, 'destroy']);

        Route::get('/committee-members', [CommitteeMemberController::class, 'index']);
        Route::post('/committee-members', [CommitteeMemberController::class, 'store']);
        Route::put('/committee-members/{committeeMember}', [CommitteeMemberController::class, 'update']);
        Route::delete('/committee-members/{committeeMember}', [CommitteeMemberController::class, 'destroy']);
    });
});
