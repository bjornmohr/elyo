<?php

use App\Http\Controllers\Admin\AdminCompanyController;
use App\Http\Controllers\Admin\AdminPartnerController;
use App\Http\Controllers\Admin\AdminPointsController;
use App\Http\Controllers\Admin\SystemExerciseController;
use App\Http\Controllers\Admin\SystemExerciseTagController;
use App\Http\Controllers\Admin\SystemMeasureTemplateController;
use App\Http\Controllers\Admin\SystemMeasureTemplateExerciseController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\InviteController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/invite/verify', [InviteController::class, 'verify']);
Route::post('/auth/invite/accept', [InviteController::class, 'accept']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // ADR-003 concretization: platform admin and system-catalog routes belong
    // to identity because their managed resources live in the identity domain.
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

        Route::get('/system-measure-templates', [SystemMeasureTemplateController::class, 'index']);
        Route::post('/system-measure-templates', [SystemMeasureTemplateController::class, 'store']);
        Route::get('/system-measure-templates/{systemMeasureTemplate}', [SystemMeasureTemplateController::class, 'show']);
        Route::patch('/system-measure-templates/{systemMeasureTemplate}', [SystemMeasureTemplateController::class, 'update']);
        Route::post('/system-measure-templates/{systemMeasureTemplate}/archive', [SystemMeasureTemplateController::class, 'archive']);
        Route::post('/system-measure-templates/{systemMeasureTemplate}/exercises', [SystemMeasureTemplateExerciseController::class, 'store']);
        Route::post('/system-measure-templates/{systemMeasureTemplate}/exercises/reorder', [SystemMeasureTemplateExerciseController::class, 'reorder']);
        Route::patch('/system-measure-templates/{systemMeasureTemplate}/exercises/{templateExercise}', [SystemMeasureTemplateExerciseController::class, 'update']);
        Route::delete('/system-measure-templates/{systemMeasureTemplate}/exercises/{templateExercise}', [SystemMeasureTemplateExerciseController::class, 'destroy']);
    });
});
