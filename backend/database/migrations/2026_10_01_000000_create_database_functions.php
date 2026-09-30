<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fonctions PostgreSQL partagées par les migrations suivantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Utilisée par les triggers "append only" (ledger, audit, timeline).
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION cashop_forbid_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'La table % est en ajout seul : % interdit', TG_TABLE_NAME, TG_OP
                    USING ERRCODE = 'integrity_constraint_violation';
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS cashop_forbid_mutation()');
    }
};
