<?php

namespace App\Support\Enums;

/**
 * Utilitaires pour les backed enums (valeurs, contraintes CHECK).
 */
trait HasValues
{
    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
