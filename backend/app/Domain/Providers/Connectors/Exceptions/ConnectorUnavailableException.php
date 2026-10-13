<?php

namespace App\Domain\Providers\Connectors\Exceptions;

use RuntimeException;

/** Aucun connecteur utilisable (officiel non implémenté, production désactivée, environnement incohérent). */
class ConnectorUnavailableException extends RuntimeException {}
