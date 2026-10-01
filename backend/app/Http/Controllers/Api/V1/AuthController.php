<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Actions\CompleteChallenge;
use App\Domain\Identity\Actions\RegisterUser;
use App\Domain\Identity\Actions\StartLogin;
use App\Domain\Identity\DTO\DeviceContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\ProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUser $register): JsonResponse
    {
        $challenge = $register($request->validated(), DeviceContext::fromArray($request->validated('device'), $request->ip()));

        return response()->json($challenge, 202);
    }

    public function login(LoginRequest $request, StartLogin $login): JsonResponse
    {
        $challenge = $login(
            $request->validated('login'),
            $request->validated('password'),
            DeviceContext::fromArray($request->validated('device'), $request->ip()),
        );

        return response()->json($challenge, 202);
    }

    public function verifyOtp(VerifyOtpRequest $request, CompleteChallenge $complete): JsonResponse
    {
        ['user' => $user, 'token' => $token] = $complete($request->validated('challenge_id'), $request->validated('code'), $request->ip());

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => new ProfileResource($user),
        ]);
    }

    public function logout(Request $request, AuditLogger $audit): Response
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        $audit->log('auth.logout', ActorType::User, $user->id, $user);

        return response()->noContent();
    }
}
