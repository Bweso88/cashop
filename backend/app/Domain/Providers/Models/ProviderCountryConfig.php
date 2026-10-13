<?php

namespace App\Domain\Providers\Models;

use App\Domain\Providers\Enums\ConfirmationStatus;
use App\Domain\Providers\Enums\ProviderDirection;
use App\Domain\Providers\Enums\ProviderMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProviderCountryConfig extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'direction' => ProviderDirection::class,
            'mode' => ProviderMode::class,
            'confirmation_status' => ConfirmationStatus::class,
            'settings' => 'array',
            'is_enabled' => 'boolean',
        ];
    }
}
