<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SecurityController;
use Illuminate\Support\Facades\Route;

/*
| API clients — /api/v1 (docs/openapi.yaml)
*/

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/verify-otp', [AuthController::class, 'verifyOtp']);
    });

    Route::middleware(['auth:sanctum', 'throttle:api-user'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::put('auth/pin', [SecurityController::class, 'setPin'])->middleware('throttle:sensitive');
        Route::post('auth/mfa/totp', [SecurityController::class, 'setupTotp']);
        Route::post('auth/mfa/totp/confirm', [SecurityController::class, 'confirmTotp'])->middleware('throttle:sensitive');
        Route::delete('auth/mfa/totp', [SecurityController::class, 'disableTotp'])->middleware('throttle:sensitive');
        Route::post('auth/device/key', [SecurityController::class, 'registerDeviceKey']);
        Route::post('auth/device/challenge', [SecurityController::class, 'deviceChallenge']);

        Route::get('profile', [ProfileController::class, 'show']);
    });
});
