<?php

namespace App\Domain\Providers\Enums;

use App\Support\Enums\HasValues;

enum ProviderCapability: string
{
    use HasValues;

    case Quote = 'quote';
    case Create = 'create';
    case Commit = 'commit';
    case Status = 'status';
    case Cancel = 'cancel';
    case Refund = 'refund';
    case Webhook = 'webhook';
    case Collection = 'collection';
    case Disbursement = 'disbursement';
    case Remittance = 'remittance';
    case NameLookup = 'name_lookup';
}
