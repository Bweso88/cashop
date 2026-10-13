<?php

namespace App\Domain\Providers\Connectors\Exceptions;

use RuntimeException;

/**
 * Erreur d'un prestataire.
 *  - definitive = true : refus explicite, l'opération n'a pas eu lieu.
 *  - definitive = false : résultat inconnu (délai, réseau, réponse illisible) : il faut vérifier
 *    auprès du prestataire avant toute nouvelle tentative.
 */
class ConnectorException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $definitive,
        public readonly ?string $failureCode = null,
    ) {
        parent::__construct($message);
    }
}
