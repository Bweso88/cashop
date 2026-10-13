<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payments\DTO\CreateTransferInput;
use App\Domain\Payments\Enums\PayoutMethod;
use App\Domain\Payments\Enums\TransferStatus;
use App\Domain\Payments\Models\Receipt;
use App\Domain\Payments\Models\Transfer;
use App\Domain\Payments\Services\QuoteService;
use App\Domain\Payments\Services\TransferOrchestrator;
use App\Domain\Providers\Enums\SimulationScenario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfers\CreateTransferRequest;
use App\Http\Requests\Transfers\QuoteRequest;
use App\Http\Resources\Transfers\QuoteResource;
use App\Http\Resources\Transfers\TransferResource;
use App\Support\Http\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    private const WITH = ['provider', 'fundingProvider', 'beneficiary', 'paymentAttempts', 'refunds', 'receipts', 'events'];

    public function quote(QuoteRequest $request, QuoteService $quotes): JsonResponse
    {
        $data = $request->validated();
        $quote = $quotes->create(
            $request->user(), $data['network'], $data['funding_provider'], $data['destination_country'],
            PayoutMethod::from($data['payout_method']), (int) $data['send_amount'],
        );

        return response()->json(new QuoteResource($quote->load('provider', 'fundingProvider')), 201);
    }

    public function store(CreateTransferRequest $request, TransferOrchestrator $orchestrator): JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || strlen($key) < 8 || strlen($key) > 100) {
            throw new ApiException('IDEMPOTENCY_KEY_REQUIRED', 'En-tête Idempotency-Key requis (8 à 100 caractères).', 400);
        }
        $data = $request->validated();
        $transfer = $orchestrator->create($request->user(), new CreateTransferInput(
            quoteId: $data['quote_id'],
            beneficiaryId: $data['beneficiary_id'],
            fundingMsisdn: $data['funding_msisdn'],
            pin: $data['pin'],
            purpose: $data['purpose'] ?? null,
            note: $data['note'] ?? null,
            scenario: isset($data['simulation_scenario']) ? SimulationScenario::from($data['simulation_scenario']) : null,
        ), $key);

        return response()->json(new TransferResource($transfer->load(self::WITH)), $transfer->wasRecentlyCreated ? 201 : 200);
    }

    /** Historique : filtres par état (en cours / terminés) et recherche par référence. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'state' => ['nullable', 'in:in_progress,completed,all'],
            'q' => ['nullable', 'string', 'max:40'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $terminal = array_map(fn (TransferStatus $s) => $s->value, array_filter(TransferStatus::cases(), fn ($s) => $s->isTerminal() || $s === TransferStatus::TransferAvailableForPickup));

        $query = Transfer::query()->where('user_id', $request->user()->id)->with('provider', 'fundingProvider', 'beneficiary')->orderByDesc('created_at');
        match ($data['state'] ?? 'all') {
            'in_progress' => $query->whereNotIn('status', $terminal),
            'completed' => $query->whereIn('status', $terminal),
            default => null,
        };
        if (! empty($data['q'])) {
            $term = strtoupper(trim($data['q']));
            $query->where(fn ($q) => $q->where('external_reference', 'like', "%{$term}%")->orWhere('provider_reference', 'like', "%{$term}%"));
        }
        $page = $query->paginate($data['per_page'] ?? 20);

        return response()->json([
            'data' => TransferResource::collection($page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, string $id): TransferResource
    {
        return new TransferResource($this->find($request, $id)->load(self::WITH));
    }

    /** Relit l'état réel chez les prestataires et fait avancer le transfert. */
    public function refresh(Request $request, string $id, TransferOrchestrator $orchestrator): TransferResource
    {
        return new TransferResource($orchestrator->advance($this->find($request, $id))->load(self::WITH));
    }

    public function receipt(Request $request, string $id): JsonResponse
    {
        $transfer = $this->find($request, $id);
        $receipts = Receipt::query()->where('transfer_id', $transfer->id)->orderBy('issued_at')->get();
        if ($receipts->isEmpty()) {
            throw new ApiException('RECEIPT_NOT_AVAILABLE', 'Le reçu sera disponible une fois l’opération confirmée par le prestataire.', 409);
        }

        return response()->json(['data' => $receipts->map(fn (Receipt $r) => [
            'number' => $r->number, 'kind' => $r->kind, 'is_simulation' => $r->is_simulation,
            'issued_at' => $r->issued_at->toIso8601String(), 'content' => $r->payload,
        ])]);
    }

    private function find(Request $request, string $id): Transfer
    {
        return Transfer::query()->where('user_id', $request->user()->id)->whereKey($id)->first()
            ?? throw new ApiException('NOT_FOUND', 'Transfert introuvable.', 404);
    }
}
