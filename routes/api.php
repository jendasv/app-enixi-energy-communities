<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MeterPointController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/meter-points', [MeterPointController::class, 'store']);
    Route::get('/meter-points', [MeterPointController::class, 'index']);
});
