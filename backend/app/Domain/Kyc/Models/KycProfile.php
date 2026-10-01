<?php

namespace App\Domain\Kyc\Models;

use App\Domain\Kyc\Enums\KycStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycProfile extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'level_code', 'status', 'verified_at', 'expires_at', 'reviewed_by', 'rejection_reason', 'risk_rating'];

    protected function casts(): array
    {
        return [
            'status' => KycStatus::class,
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
