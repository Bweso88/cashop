<?php

namespace App\Domain\Providers\Enums;

use App\Support\Enums\HasValues;

enum ProviderMode: string
{
    use HasValues;

    case Mock = 'mock';
    case Sandbox = 'sandbox';
    case Production = 'production';
}
