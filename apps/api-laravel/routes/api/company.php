<?php

use App\Http\Controllers\Company\CompanyController;
use App\Http\Controllers\Company\CompanyInvitationController;
use App\Http\Controllers\Company\CompanySurveyController;
use App\Http\Controllers\Company\MeasureController;
use App\Http\Controllers\Company\ReportController;
use App\Http\Controllers\Company\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
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
});
