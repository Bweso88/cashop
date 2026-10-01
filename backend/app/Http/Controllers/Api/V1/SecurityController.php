<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Services\DeviceKeyService;
use App\Domain\Identity\Services\MfaService;
use App\Domain\Identity\Services\PinService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SetPinRequest;
use App\Http\Requests\Auth\TotpCodeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * PIN de transaction, MFA (TOTP) et clé biométrique de l'appareil courant.
 * L'appareil courant est celui du jeton (le nom du jeton = device_id).
 */
class SecurityController extends Controller
{
    public function setPin(SetPinRequest $request, PinService $pins): Response
    {
        $pins->set($request->user(), $request->validated('pin'), $request->validated('current_pin'));

        return response()->noContent();
    }

    public function setupTotp(Request $request, MfaService $mfa): JsonResponse
    {
        return response()->json($mfa->setup($request->user()));
    }

    public function confirmTotp(TotpCodeRequest $request, MfaService $mfa): Response
    {
        $mfa->confirm($request->user(), $request->validated('code'));

        return response()->noContent();
    }

    public function disableTotp(TotpCodeRequest $request, MfaService $mfa): Response
    {
        $mfa->disable($request->user(), $request->validated('code'));

        return response()->noContent();
    }

    public function registerDeviceKey(Request $request, DeviceKeyService $devices): Response
    {
        $data = $request->validate(['public_key' => ['required', 'string', 'max:4096']]);
        $devices->registerKey($request->user(), $request->user()->currentAccessToken()->name, $data['public_key']);

        return response()->noContent();
    }

    public function deviceChallenge(Request $request, DeviceKeyService $devices): JsonResponse
    {
        return response()->json($devices->issueChallenge($request->user(), $request->user()->currentAccessToken()->name));
    }
}
