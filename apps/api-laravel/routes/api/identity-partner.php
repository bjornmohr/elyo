<?php

use App\Http\Controllers\Partner\PartnerAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/partner/register', [PartnerAuthController::class, 'register']);
Route::post('/partner/login', [PartnerAuthController::class, 'login']);

Route::middleware('auth:partner')->group(function () {
    Route::get('/partner/me', [PartnerAuthController::class, 'me']);
    Route::post('/partner/logout', [PartnerAuthController::class, 'logout']);
    Route::post('/partner/documents', function (Request $request) {
        return response()->json(['message' => 'Document uploaded']);
    });
});
