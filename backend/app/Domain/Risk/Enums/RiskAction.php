<?php

namespace App\Domain\Risk\Enums;

use App\Support\Enums\HasValues;

enum RiskAction: string
{
    use HasValues;

    case Allow = 'ALLOW';
    case Review = 'REVIEW';
    case Block = 'BLOCK';
    case StepUpAuth = 'STEP_UP_AUTH';
}
