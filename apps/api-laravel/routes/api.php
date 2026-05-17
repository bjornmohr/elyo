<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Admin\AdminCompanyController;
use App\Http\Controllers\Admin\AdminPartnerController;
use App\Http\Controllers\Company\CompanyInvitationController;
use App\Http\Controllers\Partner\PartnerAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

// Health check
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        return response()->json([
            'status' => 'up',
            'database' => 'connected',
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'down',
            'database' => 'disconnected',
            'error' => $e->getMessage(),
        ], 503);
    }
});

// Public auth routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/invite/verify', [InviteController::class, 'verify']);
Route::post('/auth/invite/accept', [InviteController::class, 'accept']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Admin routes
    Route::middleware('role:ELYO_ADMIN,ELYO_SUPPORT')->prefix('admin')->group(function () {
        Route::get('/companies', [AdminCompanyController::class, 'index']);
        Route::post('/companies', [AdminCompanyController::class, 'store']);
        Route::get('/companies/{company}', [AdminCompanyController::class, 'show']);
        Route::put('/companies/{company}', [AdminCompanyController::class, 'update']);
        Route::post('/companies/{company}/invite-company-admin', [AdminCompanyController::class, 'inviteCompanyAdmin']);

        Route::get('/partners', [AdminPartnerController::class, 'index']);
        Route::patch('/partners/{id}', [AdminPartnerController::class, 'update']);
    });

    // Company portal routes (COMPANY_OWNER, COMPANY_ADMIN, COMPANY_MANAGER)
    Route::middleware('role:COMPANY_OWNER,COMPANY_ADMIN,COMPANY_MANAGER')->prefix('company')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Company\CompanyController::class, 'dashboard']);
        Route::get('/users', [CompanyInvitationController::class, 'users']);
        Route::get('/invitations', [CompanyInvitationController::class, 'invitations']);
        Route::post('/invitations', [CompanyInvitationController::class, 'storeInvitation']);
        Route::delete('/invitations/{invite}', [CompanyInvitationController::class, 'destroyInvitation']);

        Route::get('/teams', [\App\Http\Controllers\Company\TeamController::class, 'index']);
        Route::post('/teams', [\App\Http\Controllers\Company\TeamController::class, 'store']);
        Route::get('/teams/{id}', [\App\Http\Controllers\Company\TeamController::class, 'show']);
        Route::put('/teams/{id}', [\App\Http\Controllers\Company\TeamController::class, 'update']);
        Route::delete('/teams/{id}', [\App\Http\Controllers\Company\TeamController::class, 'destroy']);
        Route::get('/teams/{teamId}/members', [\App\Http\Controllers\Company\TeamController::class, 'members']);

        Route::get('/surveys', [\App\Http\Controllers\Company\CompanySurveyController::class, 'index']);
        Route::post('/surveys', [\App\Http\Controllers\Company\CompanySurveyController::class, 'store']);
        Route::patch('/surveys/{id}', [\App\Http\Controllers\Company\CompanySurveyController::class, 'update']);
        Route::delete('/surveys/{id}', [\App\Http\Controllers\Company\CompanySurveyController::class, 'destroy']);
        Route::get('/surveys/{id}/results', [\App\Http\Controllers\Company\CompanySurveyController::class, 'results']);

        Route::get('/measures', [\App\Http\Controllers\Company\MeasureController::class, 'index']);
        Route::post('/measures', [\App\Http\Controllers\Company\MeasureController::class, 'store']);
        Route::patch('/measures/{id}', [\App\Http\Controllers\Company\MeasureController::class, 'update']);

        Route::get('/reports', [\App\Http\Controllers\Company\ReportController::class, 'index']);
    });

    // Employee routes (EMPLOYEE only)
    Route::middleware('role:EMPLOYEE')->prefix('employee')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Employee\EmployeeController::class, 'dashboard']);
        Route::get('/checkin/status', [\App\Http\Controllers\Employee\EmployeeController::class, 'checkinStatus']);
        Route::post('/checkin', [\App\Http\Controllers\Employee\EmployeeController::class, 'checkin']);
        Route::get('/history', [\App\Http\Controllers\Employee\EmployeeController::class, 'history']);
        Route::get('/profile', [\App\Http\Controllers\Employee\EmployeeController::class, 'getProfile']);
        Route::put('/profile', [\App\Http\Controllers\Employee\EmployeeController::class, 'updateProfile']);
        Route::post('/documents', [\App\Http\Controllers\Employee\EmployeeController::class, 'uploadDocument']);
        Route::get('/measures', [\App\Http\Controllers\Employee\EmployeeController::class, 'measures']);

        Route::get('/surveys', [\App\Http\Controllers\Employee\SurveyController::class, 'index']);
        Route::get('/surveys/{id}', [\App\Http\Controllers\Employee\SurveyController::class, 'show']);
        Route::get('/surveys/{id}/result', [\App\Http\Controllers\Employee\SurveyController::class, 'result']);
        Route::post('/surveys/{id}/respond', [\App\Http\Controllers\Employee\SurveyController::class, 'respond']);
    });
});

// Partner routes (separate auth system — kept as-is)
Route::post('/partner/register', [PartnerAuthController::class, 'register']);
Route::post('/partner/login', [PartnerAuthController::class, 'login']);

Route::middleware('auth:partner')->group(function () {
    Route::get('/partner/me', [PartnerAuthController::class, 'me']);
    Route::post('/partner/logout', [PartnerAuthController::class, 'logout']);
    Route::post('/partner/documents', function (Request $request) {
        return response()->json(['message' => 'Document uploaded']);
    });
});
