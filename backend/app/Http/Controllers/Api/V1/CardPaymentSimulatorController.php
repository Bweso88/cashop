<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Payments\Models\PaymentAttempt;
use App\Domain\Payments\Services\TransferOrchestrator;
use App\Domain\Providers\Connectors\Simulated\SimulatedCardConnector;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * DÉMONSTRATION : page de paiement par carte du prestataire, simulée. En production, le client est
 * redirigé vers la page sécurisée du prestataire (3-D Secure) et Cashop n'en voit que le résultat.
 * Aucune donnée de carte n'est acceptée ici.
 */
class CardPaymentSimulatorController extends Controller
{
    public function show(Request $request, string $reference, TransferOrchestrator $orchestrator): JsonResponse
    {
        [$attempt, $connector] = $this->resolve($request, $reference, $orchestrator);
        $session = $connector->session($attempt->id);

        return response()->json([
            'reference' => $attempt->id,
            'transfer_id' => $attempt->transfer_id,
            'merchant' => 'Cashop',
            'amount' => ['amount' => $attempt->amount, 'currency' => $attempt->currency],
            'status' => $session['status'] ?? 'UNKNOWN',
            'is_simulation' => true,
            'simulation_notice' => 'Page de paiement SIMULÉE : aucune carte réelle n’est débitée. En production, la carte se saisit sur la page sécurisée du prestataire.',
        ]);
    }

    public function complete(Request $request, string $reference, TransferOrchestrator $orchestrator): JsonResponse
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in(SimulatedCardConnector::OUTCOMES)],
            'card_number' => ['prohibited'], 'cvv' => ['prohibited'], 'pan' => ['prohibited'],
        ]);
        [$attempt, $connector] = $this->resolve($request, $reference, $orchestrator);

        $connector->completeSimulatedPayment($attempt->id, $data['outcome']);
        $transfer = $orchestrator->advance($attempt->transfer);

        return response()->json(['transfer_id' => $transfer->id, 'status' => $transfer->status->value]);
    }

    /** @return array{0: PaymentAttempt, 1: SimulatedCardConnector} */
    private function resolve(Request $request, string $reference, TransferOrchestrator $orchestrator): array
    {
        $attempt = PaymentAttempt::query()->whereKey($reference)->where('direction', PaymentDirection::Collection)
            ->whereHas('transfer', fn ($q) => $q->where('user_id', $request->user()->id))->first()
            ?? throw new ApiException('NOT_FOUND', 'Paiement introuvable.', 404);
        $connector = $orchestrator->wallet($attempt->transfer);
        if (! $connector instanceof SimulatedCardConnector) {
            throw new ApiException('SIMULATION_ONLY', 'Cette page n’existe qu’en démonstration, pour un paiement par carte.', 403);
        }

        return [$attempt, $connector];
    }
}
