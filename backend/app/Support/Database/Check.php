<?php

namespace App\Support\Database;

use BackedEnum;
use Illuminate\Support\Facades\DB;

/**
 * Contraintes CHECK PostgreSQL, générées à partir des enums PHP pour que la base
 * et le code acceptent exactement les mêmes valeurs.
 */
final class Check
{
    /**
     * @param  class-string<BackedEnum>  $enum
     */
    public static function enum(string $table, string $column, string $enum): void
    {
        $values = implode(', ', array_map(
            fn (BackedEnum $case) => DB::getPdo()->quote((string) $case->value),
            $enum::cases(),
        ));

        self::add($table, "{$table}_{$column}_check", "{$column} IN ({$values})");
    }

    public static function add(string $table, string $name, string $expression): void
    {
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
    }

    /** Interdit UPDATE et DELETE sur une table (données en ajout seul). */
    public static function appendOnly(string $table): void
    {
        DB::statement(<<<SQL
            CREATE TRIGGER {$table}_append_only
            BEFORE UPDATE OR DELETE ON {$table}
            FOR EACH ROW EXECUTE FUNCTION cashop_forbid_mutation()
        SQL);
    }
}
