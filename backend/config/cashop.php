<?php

/*
| Paramètres de sécurité Cashop. Les valeurs par défaut sont des choix de sécurité raisonnables,
| pas des exigences réglementaires : à valider par la sécurité et la conformité.
*/

return [

    'auth' => [
        // Durée de vie d'un jeton d'API (minutes). Le BFF web/admin et l'app mobile se reconnectent ensuite.
        'token_ttl_minutes' => (int) env('AUTH_TOKEN_TTL_MINUTES', 720),
        'staff_token_ttl_minutes' => (int) env('AUTH_STAFF_TOKEN_TTL_MINUTES', 60),
        // Verrouillage progressif après échecs de mot de passe.
        'max_failed_logins' => (int) env('AUTH_MAX_FAILED_LOGINS', 5),
        'lockout_minutes' => (int) env('AUTH_LOCKOUT_MINUTES', 15),
    ],

    'otp' => [
        'length' => 6,
        'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 5),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        // Envois maximum par destination et par heure.
        'max_sends_per_hour' => (int) env('OTP_MAX_SENDS_PER_HOUR', 5),
    ],

    'totp' => [
        'issuer' => env('TOTP_ISSUER', 'Cashop'),
        'period' => 30,
        'digits' => 6,
        // Tolérance de dérive d'horloge (pas de 30 s de part et d'autre).
        'window' => 1,
    ],

    'pin' => [
        'length' => 6,
        'max_attempts' => (int) env('PIN_MAX_ATTEMPTS', 5),
    ],

    'device' => [
        // Durée de validité d'un challenge à signer par la clé de l'appareil (secondes).
        'challenge_ttl_seconds' => 120,
    ],

    'transfers' => [
        // Durée de validité d'un devis.
        'quote_ttl_minutes' => (int) env('QUOTE_TTL_MINUTES', 15),
        // Sans confirmation de l'opérateur passé ce délai, le paiement est vérifié par rapprochement.
        'payment_timeout_minutes' => (int) env('PAYMENT_TIMEOUT_MINUTES', 10),
        // Un rapprochement non résolu passé ce délai est escaladé à l'équipe opérations.
        'reconciliation_escalation_hours' => (int) env('RECONCILIATION_ESCALATION_HOURS', 24),
    ],

    'environments' => [
        // Production désactivée par défaut : activation explicite après validation des prérequis.
        'production_enabled' => filter_var(env('CASHOP_PRODUCTION_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],

    'simulation' => [
        // Délai avant que le connecteur simulé ne confirme (validation « sur le téléphone »).
        'delay_seconds' => (int) env('SIMULATION_DELAY_SECONDS', 4),
        // Secret HMAC des notifications simulées.
        'webhook_secret' => env('WEBHOOK_SIGNING_SECRET', ''),
    ],

    // Connecteurs officiels : code prestataire => classe. Vide tant que la documentation et les
    // accès partenaires n'ont pas été obtenus (aucune intégration n'est inventée).
    'connectors' => [
        'official' => [],
    ],

    // Rôles du personnel : MFA obligatoire, jetons courts, accès à /api/admin/v1.
    'staff_roles' => ['support', 'compliance', 'finance', 'admin', 'super_admin'],
];
