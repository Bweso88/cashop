<?php

namespace App\Domain\Providers\Enums;

use App\Support\Enums\HasValues;

/** Opérations qu'un connecteur déclare savoir effectuer. Rien n'est supposé par défaut. */
enum ConnectorCapability: string
{
    use HasValues;

    case Health = 'HEALTH';
    case Quote = 'QUOTE';
    case Validate = 'VALIDATE';
    case Collect = 'COLLECT';
    case Disburse = 'DISBURSE';
    case Status = 'STATUS';
    case CreateTransfer = 'CREATE_TRANSFER';
    case Cancel = 'CANCEL';
    case Refund = 'REFUND';
    case Webhook = 'WEBHOOK';
    case Reconcile = 'RECONCILE';
}
