<?php

namespace App\Domain\Payments\Services;

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Services\PinService;
use App\Domain\Payments\Enums\IncomingTransferStatus;
use App\Domain\Payments\Models\IncomingTransfer;
use App\Domain\Providers\Connectors\ConnectorRegistry;
use App\Domain\Providers\Connectors\Contracts\IncomingTransferConnector;
use App\Domain\Providers\Connectors\DTO\ConnectorResult;
use App\Domain\Providers\Connectors\DTO\IncomingTransfer as NetworkTransfer;
use App\Domain\Providers\Connectors\Exceptions\ConnectorException;
use App\Domain\Providers\Connectors\Exceptions\ConnectorUnavailableException;
use App\Domain\Providers\Connectors\Simulated\SimulatedRemittanceConnector;
use App\Domain\Providers\Enums\ConnectorStatus;
use App\Domain\Providers\Enums\IncomingScenario;
use App\Domain\Providers\Enums\ProviderKind;
use App\Domain\Providers\Enums\ProviderMode;
use App\Domain\Providers\Models\Provider;
use App\Models\User;
use App\Support\Http\ApiException;
use App\Support\Http\CorrelationId;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Parcours B — recevoir un transfert envoyé depuis l'étranger.
 *
 * Le bénéficiaire saisit la référence communiquée par l'expéditeur ; le réseau confirme que le
 * transfert lui est destiné, puis le verse sur son portefeuille MTN ou Airtel. Cashop transmet la
 * demande et suit le résultat : il ne reçoit jamais les fonds (aucune écriture de ledger).
 */
class IncomingTransferService
{
    public function __construct(
        private readonly ConnectorRegistry $connectors,
        private readonly PinService $pins,
        private readonly AuditLogger $audit,
    ) {}

    /** DÉMONSTRATION : un proche envoie un transfert au client connecté. Simulation uniquement. */
    public function simulate(User $user, string $networkCode, int $amount, IncomingScenario $scenario): array
    {
        $network = $this->network($networkCode);
        if ($network->mode !== ProviderMode::Mock) {
            throw new ApiException('SIMULATION_ONLY', 'La simulation de transferts entrants n’existe qu’en démonstration.', 403);
        }
        $connector = $this->connector($network);
        if (! $connector instanceof SimulatedRemittanceConnector) {
            throw new ApiException('SIMULATION_ONLY', 'La simulation de transferts entrants n’existe qu’en démonstration.', 403);
        }
        $profile = $user->profile;
        if (blank($profile?->first_name) || blank($profile?->last_name)) {
            throw new ApiException('PROFILE_INCOMPLETE', 'Renseignez vos nom et prénom dans votre profil.', 422);
        }

        $reference = $connector->simulateIncoming($profile->first_name, $profile->last_name, $amount, 'XAF', 'Proche à l’étranger (simulation)', 'FR', $scenario);

        return ['network' => ['code' => $network->code, 'name' => $network->name], 'reference' => $reference, 'is_simulation' => true];
    }

    /** Vérifie auprès du réseau qu'un transfert existe et est destiné au client. */
    public function lookup(User $user, string $networkCode, string $reference): array
    {
        $this->assertVerified($user);
        $network = $this->network($networkCode);
        $reference = strtoupper(trim($reference));

        $existing = IncomingTransfer::query()->where('provider_id', $network->id)->where('network_reference', $reference)->first();
        if ($existing && $existing->user_id !== $user->id) {
            throw new ApiException('ALREADY_CLAIMED', 'Ce transfert a déjà été réclamé.', 409);
        }
        $incoming = $this->fetch($user, $network, $reference);

        return [
            'network' => ['code' => $network->code, 'name' => $network->name],
            'reference' => $incoming->reference,
            'amount' => ['amount' => $incoming->amount, 'currency' => $incoming->currency],
            'sender' => ['name' => $incoming->senderName, 'country' => $incoming->senderCountry],
            'status' => $existing ? 'CLAIMED' : $incoming->status,
            'incoming_transfer_id' => $existing?->id,
            'is_simulation' => $incoming->isSimulated,
            'simulation_notice' => $incoming->isSimulated ? 'SIMULATION — aucun fonds réel versé.' : null,
        ];
    }

    /** Demande au réseau de verser le transfert sur le portefeuille choisi. Idempotent. */
    public function claim(User $user, array $input, string $idempotencyKey): IncomingTransfer
    {
        $replay = IncomingTransfer::query()->where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first();
        if ($replay) {
            return $replay;
        }
        $this->assertVerified($user);
        $this->pins->verify($user, $input['pin']);

        $network = $this->network($input['network']);
        $wallet = Provider::byCode($input['payout_provider']);
        if ($wallet->kind !== ProviderKind::Wallet || ! $wallet->is_enabled) {
            throw new ApiException('PROVIDER_UNAVAILABLE', 'Ce portefeuille n’est pas disponible.', 422);
        }
        if ($wallet->mode !== $network->mode) {
            throw new ApiException('ENVIRONMENT_MISMATCH', 'Le portefeuille et le réseau ne sont pas dans le même environnement.', 422);
        }
        $reference = strtoupper(trim($input['reference']));
        $incoming = $this->fetch($user, $network, $reference);
        if ($incoming->status !== 'AVAILABLE') {
            throw new ApiException('ALREADY_CLAIMED', 'Ce transfert a déjà été versé.', 409);
        }

        try {
            $transfer = DB::transaction(fn () => IncomingTransfer::create([
                'user_id' => $user->id,
                'provider_id' => $network->id,
                'payout_provider_id' => $wallet->id,
                'environment' => $network->mode,
                'network_reference' => $reference,
                'sender_name' => mb_substr($incoming->senderName, 0, 120),
                'sender_country' => $incoming->senderCountry,
                'amount' => $incoming->amount,
                'currency' => $incoming->currency,
                'payout_msisdn' => $input['payout_msisdn'],
                'payout_msisdn_hash' => hash_hmac('sha256', $input['payout_msisdn'], (string) config('app.key')),
                'status' => IncomingTransferStatus::PayoutPending,
                'idempotency_key' => $idempotencyKey,
                'correlation_id' => CorrelationId::get(),
            ]));
        } catch (UniqueConstraintViolationException) {
            throw new ApiException('ALREADY_CLAIMED', 'Ce transfert a déjà été réclamé.', 409);
        }
        $this->audit->log('incoming.claimed', ActorType::User, $user->id, $transfer, ['network' => $network->code, 'wallet' => $wallet->code]);

        try {
            $result = $this->connector($network)->requestIncomingPayout($reference, $wallet->code, $input['payout_msisdn']);
            $this->apply($transfer, $result);
        } catch (ConnectorException $e) {
            $e->definitive
                ? $this->fail($transfer, $e->failureCode ?? 'PAYOUT_REJECTED', $e->getMessage())
                : $this->update($transfer, IncomingTransferStatus::RequiresReconciliation, ['failure_code' => $e->failureCode, 'failure_reason' => $e->getMessage()]);
        }

        $fresh = $transfer->fresh();
        $fresh->wasRecentlyCreated = true;

        return $fresh;
    }

    /** Relit le statut du versement auprès du réseau. */
    public function refresh(IncomingTransfer $transfer): IncomingTransfer
    {
        if ($transfer->status->isTerminal()) {
            return $transfer;
        }
        try {
            $this->apply($transfer, $this->connector($transfer->provider)->incomingPayoutStatus($transfer->network_reference));
        } catch (ConnectorException|ConnectorUnavailableException) {
            // Statut momentanément indisponible : il sera relu plus tard, rien n'est conclu.
        }

        return $transfer->fresh();
    }

    private function apply(IncomingTransfer $transfer, ConnectorResult $result): void
    {
        match ($result->status) {
            ConnectorStatus::PaidOut, ConnectorStatus::Confirmed => $this->update($transfer, IncomingTransferStatus::PaidOut, [
                'payout_reference' => $result->providerReference, 'paid_out_at' => now(), 'failure_code' => null, 'failure_reason' => null,
            ], 'incoming.paid_out'),
            ConnectorStatus::Failed, ConnectorStatus::Cancelled => $this->fail($transfer, $result->failureCode ?? 'PAYOUT_FAILED', $result->failureReason ?? 'Versement refusé par le réseau.'),
            ConnectorStatus::Pending => $this->update($transfer, IncomingTransferStatus::PayoutPending, ['payout_reference' => $result->providerReference]),
            // Réponse inattendue : jamais traitée comme un succès.
            default => $this->update($transfer, IncomingTransferStatus::RequiresReconciliation, ['failure_code' => 'UNKNOWN_STATUS']),
        };
    }

    private function fail(IncomingTransfer $transfer, string $code, string $reason): void
    {
        $this->update($transfer, IncomingTransferStatus::Failed, ['failure_code' => $code, 'failure_reason' => mb_substr($reason, 0, 255), 'failed_at' => now()], 'incoming.failed');
    }

    private function update(IncomingTransfer $transfer, IncomingTransferStatus $status, array $attributes, ?string $auditAction = null): void
    {
        $changed = $transfer->status !== $status;
        $transfer->forceFill(['status' => $status, ...$attributes])->save();
        if ($changed && $auditAction) {
            $this->audit->log($auditAction, ActorType::System, null, $transfer, ['status' => $status->value]);
        }
    }

    private function fetch(User $user, Provider $network, string $reference): NetworkTransfer
    {
        try {
            $incoming = $this->connector($network)->lookupIncoming($reference);
        } catch (ConnectorException $e) {
            if ($e->failureCode === 'NOT_FOUND') {
                throw new ApiException('INCOMING_NOT_FOUND', 'Aucun transfert ne correspond à cette référence sur ce réseau.', 404);
            }
            throw new ApiException('PROVIDER_UNAVAILABLE', 'Le réseau ne répond pas. Réessayez dans quelques instants.', 503);
        }
        // Le réseau a désigné un bénéficiaire : il doit correspondre à l'identité du client.
        $profile = $user->profile;
        if (self::normalize("{$incoming->receiverFirstName} {$incoming->receiverLastName}") !== self::normalize("{$profile?->first_name} {$profile?->last_name}")) {
            throw new ApiException('RECEIVER_MISMATCH', 'Ce transfert est destiné à une autre personne.', 403);
        }

        return $incoming;
    }

    private function network(string $code): Provider
    {
        $network = Provider::query()->where('code', $code)->first();
        if (! $network || $network->kind !== ProviderKind::Remittance || ! $network->is_enabled) {
            throw new ApiException('PROVIDER_UNAVAILABLE', 'Ce réseau n’est pas disponible.', 422);
        }

        return $network;
    }

    private function connector(Provider $network): IncomingTransferConnector
    {
        try {
            $connector = $this->connectors->remittance($network->code, $network->mode);
        } catch (ConnectorUnavailableException $e) {
            throw new ApiException('PROVIDER_UNAVAILABLE', $e->getMessage(), 503);
        }
        if (! $connector instanceof IncomingTransferConnector) {
            throw new ApiException('PROVIDER_UNAVAILABLE', "La réception via {$network->name} n’est pas encore disponible.", 503);
        }

        return $connector;
    }

    private function assertVerified(User $user): void
    {
        if ($user->kycProfile?->status->value !== 'VERIFIED') {
            throw new ApiException('KYC_REQUIRED', 'Vérifiez votre identité avant de recevoir de l’argent.', 403);
        }
    }

    private static function normalize(string $name): string
    {
        return preg_replace('/\s+/', ' ', trim(strtolower(Str::ascii($name))));
    }
}
