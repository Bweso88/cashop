<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BeneficiaryController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SecurityController;
use App\Http\Controllers\Api\V1\TransferController;
use App\Http\Controllers\Api\V1\WebhookController;
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

        Route::get('catalog/funding-providers', [CatalogController::class, 'fundingProviders']);
        Route::get('catalog/networks', [CatalogController::class, 'networks']);
        Route::get('catalog/networks/{network}/fields', [CatalogController::class, 'fields']);
        Route::get('catalog/simulation-scenarios', [CatalogController::class, 'scenarios']);

        Route::apiResource('beneficiaries', BeneficiaryController::class);

        Route::post('transfers/quote', [TransferController::class, 'quote']);
        Route::post('transfers', [TransferController::class, 'store'])->middleware('throttle:sensitive');
        Route::get('transfers', [TransferController::class, 'index']);
        Route::get('transfers/{id}', [TransferController::class, 'show']);
        Route::post('transfers/{id}/refresh', [TransferController::class, 'refresh']);
        Route::get('transfers/{id}/receipt', [TransferController::class, 'receipt']);
    });

    // Notifications des prestataires : authentifiées par leur propre mécanisme (pas de jeton Cashop).
    // POST et PUT : MTN MoMo envoie ses notifications avec l'une ou l'autre méthode (pages Callback / API User).
    Route::match(['post', 'put'], 'webhooks/{provider}/{secret?}', WebhookController::class)->middleware('throttle:webhooks');
});
