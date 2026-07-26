<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();

        return response()->json([
            'status' => 'up',
            'runtime' => config('database.runtime'),
        ]);
    } catch (Throwable) {
        return response()->json([
            'status' => 'down',
            'runtime' => config('database.runtime'),
        ], 503);
    }
});
