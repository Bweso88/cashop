<?php

namespace App\Providers;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Services\LogOtpSender;
use App\Domain\Identity\Services\Totp;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Totp::class, fn () => Totp::fromConfig());

        // Contexte de la requête courante (IP, user-agent) injecté dans chaque entrée d'audit.
        $this->app->scoped(AuditLogger::class, fn ($app) => new AuditLogger(
            $app->runningInConsole() ? null : $app['request']->ip(),
            $app->runningInConsole() ? null : $app['request']->userAgent(),
        ));

        $this->app->bind(OtpSender::class, fn () => match (config('services.sms.provider')) {
            'log' => new LogOtpSender,
            // Fournisseur SMS à choisir : NOT CONFIRMED. Aucun envoi silencieux par défaut.
            default => throw new RuntimeException('Aucun fournisseur SMS configuré (SMS_PROVIDER).'),
        });
    }

    public function boot(): void
    {
        Password::defaults(function () {
            $rule = Password::min(10)->letters()->numbers();

            // Vérification contre les fuites connues (k-anonymity) : appel réseau, production uniquement.
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('login:'.strtolower((string) $request->input('login', $request->input('email', '')))),
        ]);
        RateLimiter::for('api-user', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(5)->by($request->user()?->id ?: $request->ip()));
    }
}
