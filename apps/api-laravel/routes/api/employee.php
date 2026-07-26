<?php

use App\Http\Controllers\Employee\EmployeeController;
use App\Http\Controllers\Employee\LabMarkerController;
use App\Http\Controllers\Employee\SurveyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
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

        // Lab markers: own data only (ELYO-102 §1.5). No company, admin or
        // reporting counterpart exists — lab values are never reportable.
        Route::get('/lab-markers', [LabMarkerController::class, 'index']);
        Route::post('/lab-markers', [LabMarkerController::class, 'store']);
        Route::get('/lab-markers/{markerKey}/history', [LabMarkerController::class, 'history'])
            ->where('markerKey', '[a-z0-9_]+');
        Route::delete('/lab-markers/{reading}', [LabMarkerController::class, 'destroy']);

        Route::get('/surveys', [SurveyController::class, 'index']);
        Route::get('/surveys/{id}', [SurveyController::class, 'show']);
        Route::get('/surveys/{id}/result', [SurveyController::class, 'result']);
        Route::post('/surveys/{id}/respond', [SurveyController::class, 'respond']);
    });
});
