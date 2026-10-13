<?php

namespace App\Domain\Providers\Models;

use App\Domain\Providers\Enums\ProviderHealthStatus;
use App\Domain\Providers\Enums\ProviderKind;
use App\Domain\Providers\Enums\ProviderMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => ProviderKind::class,
            'mode' => ProviderMode::class,
            'health_status' => ProviderHealthStatus::class,
            'is_enabled' => 'boolean',
            'health_checked_at' => 'datetime',
        ];
    }

    public static function byCode(string $code): self
    {
        return static::query()->where('code', $code)->firstOrFail();
    }

    public function countryConfigs(): HasMany
    {
        return $this->hasMany(ProviderCountryConfig::class);
    }
}
