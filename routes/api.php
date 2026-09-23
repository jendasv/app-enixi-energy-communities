<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EnergyCommunityController;
use App\Http\Controllers\MeterPointController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/meter-points', [MeterPointController::class, 'store']);
    Route::get('/meter-points', [MeterPointController::class, 'index']);

    Route::post('/energy-communities', [EnergyCommunityController::class, 'store']);
    Route::get('/energy-communities', [EnergyCommunityController::class, 'index']);
    Route::get('/energy-communities/{energyCommunity}', [EnergyCommunityController::class, 'show']);
    Route::post('/energy-communities/{energyCommunity}/users', [EnergyCommunityController::class, 'addUser']);

    Route::post('/energy-communities/{energyCommunity}/meter-points', [RegistrationController::class, 'store']);
    Route::get('/energy-communities/{energyCommunity}/meter-points', [RegistrationController::class, 'index']);
    Route::post('/registrations/{registration}/transition', [RegistrationController::class, 'transition']);
    Route::delete('/registrations/{registration}', [RegistrationController::class, 'destroy']);
});
