<?php

namespace App\Support\Http;

use Illuminate\Support\Str;

/**
 * Identifiant de corrélation de la requête courante, propagé dans les logs, les jobs,
 * les appels providers et les événements.
 */
final class CorrelationId
{
    private static ?string $current = null;

    public static function get(): string
    {
        return self::$current ??= (string) Str::uuid7();
    }

    public static function set(string $id): void
    {
        self::$current = $id;
    }

    public static function reset(): void
    {
        self::$current = null;
    }
}
