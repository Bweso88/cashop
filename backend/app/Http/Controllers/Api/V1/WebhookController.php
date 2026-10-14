<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payments\Models\PaymentAttempt;
use App\Domain\Payments\Models\Transfer;
use App\Domain\Payments\Services\TransferOrchestrator;
use App\Domain\Providers\Connectors\ConnectorRegistry;
use App\Domain\Providers\Connectors\Exceptions\ConnectorUnavailableException;
use App\Domain\Providers\Connectors\Exceptions\InvalidWebhookException;
use App\Domain\Providers\Models\Provider;
use App\Domain\Webhooks\Enums\WebhookStatus;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Notifications des prestataires. Pipeline :
 *  1. authentification selon le mécanisme du prestataire (signature, horodatage) ;
 *  2. enregistrement du payload, anti-rejeu (même événement ou même contenu = traité une fois) ;
 *  3. la notification n'est qu'un signal : le statut est relu auprès du prestataire.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, ConnectorRegistry $registry, TransferOrchestrator $orchestrator, string $provider, ?string $secret = null): JsonResponse
    {
        $model = Provider::query()->where('code', $provider)->first() ?? throw new ApiException('NOT_FOUND', 'Prestataire inconnu.', 404);

        try {
            $connector = $model->kind->value === 'WALLET' ? $registry->wallet($model->code, $model->mode) : $registry->remittance($model->code, $model->mode);
            $notification = $connector->verifyWebhook($request);
        } catch (InvalidWebhookException|ConnectorUnavailableException $e) {
            DB::table('webhooks')->insert($this->row($model, $request, null, WebhookStatus::Rejected, false, $e->getMessage()));
            throw new ApiException('INVALID_WEBHOOK', 'Notification refusée.', 401);
        }

        $eventId = (string) Str::uuid7();
        try {
            // Transaction (ou point de sauvegarde) : un doublon ne doit pas invalider une transaction englobante.
            DB::transaction(fn () => DB::table('webhooks')->insert($this->row($model, $request, $notification->eventId, WebhookStatus::Received, true, null, $eventId)));
        } catch (UniqueConstraintViolationException) {
            return response()->json(['status' => 'duplicate']); // déjà reçu : aucun nouveau traitement
        }

        $transfer = $this->transferFor($notification->reference);
        if ($transfer) {
            $orchestrator->advance($transfer);
        }
        DB::table('webhooks')->where('id', $eventId)->update([
            'status' => ($transfer ? WebhookStatus::Processed : WebhookStatus::Ignored)->value,
            'transfer_id' => $transfer?->id,
            'processed_at' => now(),
        ]);

        return response()->json(['status' => 'received', 'event_id' => $eventId]);
    }

    private function transferFor(?string $reference): ?Transfer
    {
        if (! $reference) {
            return null;
        }
        if (Str::isUuid($reference) && ($attempt = PaymentAttempt::query()->find($reference))) {
            return $attempt->transfer;
        }

        return Transfer::query()->where('external_reference', $reference)->first();
    }

    private function row(Provider $provider, Request $request, ?string $providerEventId, WebhookStatus $status, bool $valid, ?string $error, ?string $id = null): array
    {
        $body = $request->getContent();

        return [
            'id' => $id ?? (string) Str::uuid7(),
            'provider_id' => $provider->id,
            'provider_event_id' => $providerEventId,
            'signature_valid' => $valid,
            'received_at' => now(),
            'headers' => json_encode(collect($request->headers->all())->except(['authorization', 'cookie'])->map(fn ($v) => $v[0] ?? null)->all()),
            'payload' => json_validate($body) ? $body : json_encode(['raw' => mb_substr($body, 0, 2000)]),
            'payload_sha256' => hash('sha256', $body),
            'status' => $status->value,
            'error' => $error,
            'source_ip' => $request->ip(),
        ];
    }
}
