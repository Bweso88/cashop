<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\DevicePlatform;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'device_id', 'platform', 'name', 'public_key', 'push_token', 'trusted_at', 'last_seen_at', 'revoked_at'];

    protected $hidden = ['push_token'];

    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'trusted_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
