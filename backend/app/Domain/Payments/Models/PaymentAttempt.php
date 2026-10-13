<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Providers\Enums\ProviderMode;
use App\Domain\Providers\Models\Provider;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mouvement Mobile Money. L'identifiant sert de référence externe auprès de l'opérateur :
 * réinterroger l'opérateur avec cette référence ne crée jamais de second débit.
 */
class PaymentAttempt extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['account'];

    protected function casts(): array
    {
        return [
            'direction' => PaymentDirection::class,
            'status' => PaymentAttemptStatus::class,
            'environment' => ProviderMode::class,
            'account' => 'encrypted',
            'confirmed_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }
}
