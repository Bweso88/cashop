<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PayoutMethod;
use App\Domain\Payments\Enums\QuoteStatus;
use App\Domain\Providers\Enums\ProviderMode;
use App\Domain\Providers\Models\Provider;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferQuote extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'environment' => ProviderMode::class,
            'payment_method' => PaymentMethod::class,
            'payout_method' => PayoutMethod::class,
            'status' => QuoteStatus::class,
            'fee_breakdown' => 'array',
            'is_simulated' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function fundingProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'funding_provider_id');
    }

    public function isUsable(): bool
    {
        return $this->status === QuoteStatus::Active && $this->expires_at->isFuture();
    }
}
