<?php

namespace App\Domain\Payments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bénéficiaire enregistré. Nom, téléphone, ville et détails sont chiffrés en base ;
 * identity_hash (HMAC) permet de détecter un doublon sans déchiffrer.
 */
class Beneficiary extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['user_id', 'first_name', 'last_name', 'phone_e164', 'country_code', 'city', 'relationship', 'details', 'identity_hash', 'display_name', 'last_used_at'];

    protected function casts(): array
    {
        return [
            'first_name' => 'encrypted',
            'last_name' => 'encrypted',
            'phone_e164' => 'encrypted',
            'city' => 'encrypted',
            'details' => 'encrypted:array',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public static function identityHash(string $firstName, string $lastName, string $country, ?string $phone): string
    {
        $normalized = mb_strtolower(trim($firstName).'|'.trim($lastName).'|'.$country.'|'.($phone ?? ''));

        return hash_hmac('sha256', $normalized, (string) config('app.key'));
    }

    public static function displayNameFor(string $firstName, string $lastName): string
    {
        return trim($firstName).' '.mb_strtoupper(mb_substr(trim($lastName), 0, 1)).'.';
    }
}
