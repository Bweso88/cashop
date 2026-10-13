<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PayoutMethod;
use App\Domain\Payments\Enums\TransferStatus;
use App\Domain\Providers\Enums\ProviderMode;
use App\Domain\Providers\Models\Provider;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Transfert. Le statut ne se modifie que via TransferStateMachine (transitions vérifiées et tracées).
 */
class Transfer extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['funding_account', 'pickup_code'];

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'environment' => ProviderMode::class,
            'payment_method' => PaymentMethod::class,
            'payout_method' => PayoutMethod::class,
            'funding_account' => 'encrypted',
            'pickup_code' => 'encrypted',
            'payment_confirmed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'next_status_poll_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(TransferQuote::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class)->withTrashed();
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function fundingProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'funding_provider_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TransferEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function collection(): HasOne
    {
        return $this->hasOne(PaymentAttempt::class)->where('direction', PaymentDirection::Collection);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function isSimulated(): bool
    {
        return $this->environment === ProviderMode::Mock;
    }
}
