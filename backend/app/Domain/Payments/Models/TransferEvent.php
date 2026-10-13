<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\TransferEventSource;
use App\Domain\Payments\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Événement de la timeline (table en ajout seul). */
class TransferEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from_status' => TransferStatus::class,
            'to_status' => TransferStatus::class,
            'source' => TransferEventSource::class,
            'payload' => 'array',
        ];
    }
}
