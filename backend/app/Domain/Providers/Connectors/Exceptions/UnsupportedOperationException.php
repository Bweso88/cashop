<?php

namespace App\Domain\Providers\Connectors\Exceptions;

use RuntimeException;

/** Le prestataire ne propose pas cette opération (capacité non déclarée). */
class UnsupportedOperationException extends RuntimeException {}
