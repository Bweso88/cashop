<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\EnsureStaffMfa;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Http\ApiException;
use App\Support\Http\CorrelationId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Format d'erreur unique de l'API : { code, message, errors?, correlation_id }. */
$error = fn (string $code, string $message, int $status, array $errors = [], array $headers = []) => response()->json(
    array_filter([
        'code' => $code,
        'message' => $message,
        'errors' => $errors ?: null,
        'correlation_id' => CorrelationId::get(),
    ], fn ($v) => $v !== null),
    $status,
    $headers + ['X-Correlation-Id' => CorrelationId::get()],
);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::prefix('api/admin/v1')
                ->middleware(['api', 'auth:sanctum', 'staff.mfa', 'throttle:api-user'])
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignCorrelationId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'staff.mfa' => EnsureStaffMfa::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($error): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => true);
        $exceptions->dontReport([ApiException::class]);

        $exceptions->render(fn (ApiException $e) => $error($e->errorCode, $e->getMessage(), $e->status, $e->errors, $e->headers));
        $exceptions->render(fn (ValidationException $e) => $error('VALIDATION_ERROR', 'Données invalides.', 422, $e->errors()));
        $exceptions->render(fn (AuthenticationException $e) => $error('UNAUTHENTICATED', 'Authentification requise.', 401));
        $exceptions->render(fn (AuthorizationException $e) => $error('FORBIDDEN', 'Action non autorisée.', 403));
        $exceptions->render(fn (UnauthorizedException $e) => $error('FORBIDDEN', 'Action non autorisée.', 403));
        $exceptions->render(fn (ThrottleRequestsException $e) => $error('TOO_MANY_REQUESTS', 'Trop de requêtes. Réessayez plus tard.', 429, [], $e->getHeaders()));
        $exceptions->render(fn (NotFoundHttpException $e) => $error('NOT_FOUND', 'Ressource introuvable.', 404));
        $exceptions->render(function (Throwable $e) use ($error) {
            if ($e instanceof HttpExceptionInterface) {
                return $error('HTTP_ERROR', $e->getMessage() ?: 'Erreur.', $e->getStatusCode(), [], $e->getHeaders());
            }

            // Jamais de détail interne exposé au client ; l'erreur est journalisée avec le correlation ID.
            return config('app.debug') ? null : $error('INTERNAL_ERROR', 'Erreur interne. Référence : '.CorrelationId::get(), 500);
        });
    })->create();
