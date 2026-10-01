<?php

namespace App\Http\Middleware;

use App\Support\Http\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zone admin : réservée au personnel, MFA activée obligatoire.
 */
class EnsureStaffMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isStaff() || ! $user->tokenCan('staff')) {
            throw ApiException::forbidden('STAFF_ONLY', 'Accès réservé au personnel Cashop.');
        }
        if (! $user->hasMfaEnabled()) {
            throw ApiException::forbidden('MFA_REQUIRED', 'Activez l’authentification à deux facteurs pour accéder au back-office.');
        }

        return $next($request);
    }
}
