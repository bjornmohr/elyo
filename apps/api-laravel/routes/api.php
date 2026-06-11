<?php

use App\Http\Controllers\Admin\AdminCompanyController;
use App\Http\Controllers\Admin\AdminPartnerController;
use App\Http\Controllers\Admin\AdminPointsController;
use App\Http\Controllers\Admin\SystemExerciseController;
use App\Http\Controllers\Admin\SystemExerciseTagController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Company\CompanyController;
use App\Http\Controllers\Company\CompanyInvitationController;
use App\Http\Controllers\Company\CompanySurveyController;
use App\Http\Controllers\Company\MeasureController;
use App\Http\Controllers\Company\ReportController;
use App\Http\Controllers\Company\TeamController;
use App\Http\Controllers\Employee\EmployeeController;
use App\Http\Controllers\Employee\SurveyController;
use App\Http\Controllers\Partner\PartnerAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();

        return response()->json([
            'status' => 'up',
            'database' => 'connected',
        ]);
    } catch (Exception $e) {
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
        Route::get('/points-config', [AdminPointsController::class, 'index']);
        Route::put('/points-config', [AdminPointsController::class, 'update']);

        Route::get('/system-exercises', [SystemExerciseController::class, 'index']);
        Route::post('/system-exercises', [SystemExerciseController::class, 'store']);
        Route::get('/system-exercises/{systemExercise}', [SystemExerciseController::class, 'show']);
        Route::patch('/system-exercises/{systemExercise}', [SystemExerciseController::class, 'update']);
        Route::post('/system-exercises/{systemExercise}/archive', [SystemExerciseController::class, 'archive']);
        Route::get('/system-exercise-tags', [SystemExerciseTagController::class, 'index']);
    });

    // Company portal routes (COMPANY_OWNER, COMPANY_ADMIN, COMPANY_MANAGER)
    Route::middleware(['role:COMPANY_OWNER,COMPANY_ADMIN,COMPANY_MANAGER', 'portal:company'])->prefix('company')->group(function () {
        Route::get('/dashboard', [CompanyController::class, 'dashboard']);
        Route::get('/users', [CompanyInvitationController::class, 'users']);
        Route::get('/invitations', [CompanyInvitationController::class, 'invitations']);
        Route::post('/invitations', [CompanyInvitationController::class, 'storeInvitation']);
        Route::delete('/invitations/{invite}', [CompanyInvitationController::class, 'destroyInvitation']);

        Route::get('/teams', [TeamController::class, 'index']);
        Route::post('/teams', [TeamController::class, 'store']);
        Route::get('/teams/{id}', [TeamController::class, 'show']);
        Route::put('/teams/{id}', [TeamController::class, 'update']);
        Route::delete('/teams/{id}', [TeamController::class, 'destroy']);
        Route::get('/teams/{teamId}/members', [TeamController::class, 'members']);

        Route::get('/surveys', [CompanySurveyController::class, 'index']);
        Route::post('/surveys', [CompanySurveyController::class, 'store']);
        Route::get('/surveys/{id}', [CompanySurveyController::class, 'show']);
        Route::patch('/surveys/{id}', [CompanySurveyController::class, 'update']);
        Route::post('/surveys/{id}/activate', [CompanySurveyController::class, 'activate']);
        Route::delete('/surveys/{id}', [CompanySurveyController::class, 'destroy']);
        Route::get('/surveys/{id}/results', [CompanySurveyController::class, 'results']);

        Route::get('/measures', [MeasureController::class, 'index']);
        Route::post('/measures', [MeasureController::class, 'store']);
        Route::post('/measures/{measure}/checkin-token', [MeasureController::class, 'rotateCheckinToken']);
        Route::get('/measures/{id}/participation-summary', [MeasureController::class, 'participationSummary']);
        Route::patch('/measures/{id}', [MeasureController::class, 'update']);

        Route::get('/reports', [ReportController::class, 'index']);
    });

    // Employee routes (EMPLOYEE only)
    Route::middleware('role:EMPLOYEE')->prefix('employee')->group(function () {
        Route::get('/dashboard', [EmployeeController::class, 'dashboard']);
        Route::get('/checkin/status', [EmployeeController::class, 'checkinStatus']);
        Route::post('/checkin', [EmployeeController::class, 'checkin']);
        Route::get('/history', [EmployeeController::class, 'history']);
        Route::get('/profile', [EmployeeController::class, 'getProfile']);
        Route::put('/profile', [EmployeeController::class, 'updateProfile']);
        Route::post('/documents', [EmployeeController::class, 'uploadDocument']);
        Route::get('/measures', [EmployeeController::class, 'measures']);
        Route::post('/measures/{measure}/participate', [EmployeeController::class, 'participateInMeasure']);
        Route::post('/measure-checkins/{token}', [EmployeeController::class, 'redeemMeasureCheckin']);

        Route::get('/surveys', [SurveyController::class, 'index']);
        Route::get('/surveys/{id}', [SurveyController::class, 'show']);
        Route::get('/surveys/{id}/result', [SurveyController::class, 'result']);
        Route::post('/surveys/{id}/respond', [SurveyController::class, 'respond']);
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
