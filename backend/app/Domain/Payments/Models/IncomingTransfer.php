<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\IncomingTransferStatus;
use App\Domain\Providers\Enums\ProviderMode;
use App\Domain\Providers\Models\Provider;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Réception d'un transfert venu de l'étranger, versé par le réseau sur un portefeuille Mobile Money. */
class IncomingTransfer extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['payout_msisdn'];

    protected function casts(): array
    {
        return [
            'status' => IncomingTransferStatus::class,
            'environment' => ProviderMode::class,
            'payout_msisdn' => 'encrypted',
            'paid_out_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function payoutProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'payout_provider_id');
    }

    public function isSimulated(): bool
    {
        return $this->environment === ProviderMode::Mock;
    }
}
