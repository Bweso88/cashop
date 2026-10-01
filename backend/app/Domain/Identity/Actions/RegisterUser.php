<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\DTO\DeviceContext;
use App\Domain\Identity\Enums\OtpChannel;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Services\OtpService;
use App\Domain\Kyc\Enums\KycStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inscription : crée le compte (téléphone non vérifié) et envoie un OTP par SMS.
 *
 * Anti-énumération : si l'e-mail ou le téléphone est déjà utilisé, la réponse est identique
 * (un challenge factice est renvoyé, aucun code n'est envoyé ; la validation échouera).
 */
class RegisterUser
{
    public function __construct(private readonly OtpService $otp, private readonly AuditLogger $audit) {}

    /**
     * @param  array{email: string, phone: string, password: string, first_name?: string, last_name?: string, country: string}  $data
     * @return array{challenge_id: string, channel: string, destination_hint: string|null, expires_at: string}
     */
    public function __invoke(array $data, DeviceContext $device): array
    {
        $exists = User::query()->where('email', $data['email'])->orWhere('phone_e164', $data['phone'])->exists();
        if ($exists) {
            $this->audit->log('auth.register.duplicate', ActorType::System, changes: ['ip' => $device->ip]);

            return [
                'challenge_id' => (string) Str::uuid7(),
                'channel' => OtpChannel::Sms->value,
                'destination_hint' => substr($data['phone'], 0, 4).str_repeat('•', max(strlen($data['phone']) - 6, 0)).substr($data['phone'], -2),
                'expires_at' => now()->addMinutes(config('cashop.otp.ttl_minutes'))->toIso8601String(),
            ];
        }

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'email' => $data['email'],
                'phone_e164' => $data['phone'],
                'password' => $data['password'],
            ]);
            $user->assignRole('customer');
            $currency = DB::table('countries')->where('code', $data['country'])->value('default_currency');
            $user->profile()->create([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'country_of_residence' => $data['country'],
                'preferred_currency' => $currency,
            ]);
            $user->kycProfile()->create(['level_code' => 'LEVEL_0', 'status' => KycStatus::Pending]);

            return $user;
        });

        $this->audit->log('auth.registered', ActorType::User, $user->id, $user);

        $issued = $this->otp->issue($user, OtpChannel::Sms, $user->phone_e164, OtpPurpose::Register, $device->toArray());

        return [
            'challenge_id' => $issued['challenge']->id,
            'channel' => OtpChannel::Sms->value,
            'destination_hint' => $issued['destination_hint'],
            'expires_at' => $issued['challenge']->expires_at->toIso8601String(),
        ];
    }
}
