<?php

namespace App\Support\Http;

use RuntimeException;

/**
 * Erreur métier renvoyée au client au format { code, message, errors, correlation_id }.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 400,
        public readonly array $errors = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($message);
    }

    public static function unauthenticated(string $message = 'Authentification requise.'): self
    {
        return new self('UNAUTHENTICATED', $message, 401);
    }

    public static function forbidden(string $code = 'FORBIDDEN', string $message = 'Action non autorisée.'): self
    {
        return new self($code, $message, 403);
    }
}
