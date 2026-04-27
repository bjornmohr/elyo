<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Partner\PartnerAuthController;
use App\Http\Controllers\Admin\AdminPartnerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/invite/verify/{token}', [InviteController::class, 'verify']);
Route::post('/auth/invite/accept', [InviteController::class, 'accept']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Admin routes
    Route::middleware('role:ELYO_ADMIN')->group(function () {
        Route::get('/admin/stats', function () {
            return response()->json(['message' => 'Admin only stats']);
        });
        Route::get('/admin/partners', [AdminPartnerController::class, 'index']);
        Route::patch('/admin/partners/{id}', [AdminPartnerController::class, 'update']);
    });

    // Company & HR routes
    Route::middleware('role:COMPANY_ADMIN,COMPANY_MANAGER,ELYO_ADMIN')->group(function () {
        Route::get('/company/dashboard', [\App\Http\Controllers\Company\CompanyController::class, 'dashboard']);

        Route::get('/company/teams', [\App\Http\Controllers\Company\TeamController::class, 'index']);
        Route::post('/company/teams', [\App\Http\Controllers\Company\TeamController::class, 'store']);
        Route::get('/company/teams/{id}', [\App\Http\Controllers\Company\TeamController::class, 'show']);
        Route::put('/company/teams/{id}', [\App\Http\Controllers\Company\TeamController::class, 'update']);
        Route::delete('/company/teams/{id}', [\App\Http\Controllers\Company\TeamController::class, 'destroy']);
        Route::get('/company/teams/{teamId}/members', [\App\Http\Controllers\Company\TeamController::class, 'members']);

        Route::get('/company/surveys', [\App\Http\Controllers\Company\CompanySurveyController::class, 'index']);
        Route::post('/company/surveys', [\App\Http\Controllers\Company\CompanySurveyController::class, 'store']);
        Route::patch('/company/surveys/{id}', [\App\Http\Controllers\Company\CompanySurveyController::class, 'update']);
        Route::delete('/company/surveys/{id}', [\App\Http\Controllers\Company\CompanySurveyController::class, 'destroy']);
        Route::get('/company/surveys/{id}/results', [\App\Http\Controllers\Company\CompanySurveyController::class, 'results']);

        Route::get('/company/measures', [\App\Http\Controllers\Company\MeasureController::class, 'index']);
        Route::post('/company/measures', [\App\Http\Controllers\Company\MeasureController::class, 'store']);
        Route::patch('/company/measures/{id}', [\App\Http\Controllers\Company\MeasureController::class, 'update']);

        Route::get('/company/reports', [\App\Http\Controllers\Company\ReportController::class, 'index']);
    });

    // Employee routes
    Route::middleware('role:EMPLOYEE,COMPANY_MANAGER,COMPANY_ADMIN,ELYO_ADMIN')->group(function () {
        Route::get('/employee/dashboard', [\App\Http\Controllers\Employee\EmployeeController::class, 'dashboard']);
        Route::post('/employee/checkin', [\App\Http\Controllers\Employee\EmployeeController::class, 'checkin']);
        Route::get('/employee/history', [\App\Http\Controllers\Employee\EmployeeController::class, 'history']);
        Route::get('/employee/profile', [\App\Http\Controllers\Employee\EmployeeController::class, 'getProfile']);
        Route::put('/employee/profile', [\App\Http\Controllers\Employee\EmployeeController::class, 'updateProfile']);

        Route::get('/employee/surveys', [\App\Http\Controllers\Employee\SurveyController::class, 'index']);
        Route::get('/employee/surveys/{id}', [\App\Http\Controllers\Employee\SurveyController::class, 'show']);
        Route::post('/employee/surveys/{id}/respond', [\App\Http\Controllers\Employee\SurveyController::class, 'respond']);
    });
});

// Partner routes
Route::post('/partner/register', [PartnerAuthController::class, 'register']);
Route::post('/partner/login', [PartnerAuthController::class, 'login']);

Route::middleware('auth:partner')->group(function () {
    Route::get('/partner/me', [PartnerAuthController::class, 'me']);
    Route::post('/partner/logout', [PartnerAuthController::class, 'logout']);
    // Documents upload placeholder
    Route::post('/partner/documents', function (Request $request) {
        return response()->json(['message' => 'Document uploaded']);
    });
});
