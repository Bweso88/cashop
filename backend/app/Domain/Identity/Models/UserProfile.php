<?php

namespace App\Domain\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Profil personnel. Les champs nominatifs sont chiffrés en base (casts "encrypted").
 */
class UserProfile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id', 'first_name', 'last_name', 'date_of_birth', 'nationality', 'country_of_residence',
        'address_line1', 'address_line2', 'city', 'postal_code', 'preferred_currency', 'locale',
    ];

    protected function casts(): array
    {
        return [
            'first_name' => 'encrypted',
            'last_name' => 'encrypted',
            'date_of_birth' => 'encrypted',
            'address_line1' => 'encrypted',
            'address_line2' => 'encrypted',
            'city' => 'encrypted',
            'postal_code' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
