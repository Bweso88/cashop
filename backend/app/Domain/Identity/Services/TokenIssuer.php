<?php

namespace App\Domain\Identity\Services;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

/**
 * Jetons d'API Sanctum : un jeton par appareil, portée "customer" ou "staff", durée limitée.
 * Le BFF web/admin conserve le jeton côté serveur (cookie httpOnly) : il n'atteint jamais le navigateur.
 */
class TokenIssuer
{
    public function issue(User $user, string $deviceId): NewAccessToken
    {
        $staff = $user->isStaff();
        // Une nouvelle connexion sur un appareil remplace son ancien jeton.
        $user->tokens()->where('name', $deviceId)->delete();

        return $user->createToken(
            $deviceId,
            [$staff ? 'staff' : 'customer'],
            now()->addMinutes(config($staff ? 'cashop.auth.staff_token_ttl_minutes' : 'cashop.auth.token_ttl_minutes')),
        );
    }
}
