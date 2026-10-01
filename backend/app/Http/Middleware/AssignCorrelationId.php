<?php

namespace App\Http\Middleware;

use App\Support\Http\CorrelationId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reprend X-Correlation-Id (s'il est un UUID valide) ou en génère un, l'ajoute au contexte
 * des logs et le renvoie dans la réponse.
 */
class AssignCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get('X-Correlation-Id');
        CorrelationId::reset();
        if (is_string($incoming) && Str::isUuid($incoming)) {
            CorrelationId::set(strtolower($incoming));
        }

        $id = CorrelationId::get();
        Log::shareContext(['correlation_id' => $id]);

        $response = $next($request);
        $response->headers->set('X-Correlation-Id', $id);

        return $response;
    }
}
