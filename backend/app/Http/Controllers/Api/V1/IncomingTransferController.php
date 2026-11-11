<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payments\Models\IncomingTransfer;
use App\Domain\Payments\Services\IncomingTransferService;
use App\Domain\Providers\Enums\IncomingScenario;
use App\Http\Controllers\Controller;
use App\Http\Resources\Transfers\IncomingTransferResource;
use App\Support\Http\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Parcours B : recevoir un transfert envoyé depuis l'étranger, versé sur Mobile Money par le réseau. */
class IncomingTransferController extends Controller
{
    public function __construct(private readonly IncomingTransferService $service) {}

    public function simulate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'network' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1000', 'max:5000000'],
            'scenario' => ['nullable', Rule::enum(IncomingScenario::class)],
        ]);

        return response()->json($this->service->simulate(
            $request->user(), $data['network'], $data['amount'], IncomingScenario::tryFrom($data['scenario'] ?? '') ?? IncomingScenario::Success,
        ), 201);
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'network' => ['required', 'string'],
            'reference' => ['required', 'string', 'min:6', 'max:64'],
        ]);

        return response()->json($this->service->lookup($request->user(), $data['network'], $data['reference']));
    }

    public function store(Request $request): JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || strlen($key) < 8 || strlen($key) > 100) {
            throw new ApiException('IDEMPOTENCY_KEY_REQUIRED', 'En-tête Idempotency-Key requis (8 à 100 caractères).', 400);
        }
        $data = $request->validate([
            'network' => ['required', 'string'],
            'reference' => ['required', 'string', 'min:6', 'max:64'],
            'payout_provider' => ['required', 'string', 'in:mtn_momo,airtel_money'],
            // Versement sur un numéro congolais (+242), comme pour le paiement d'un envoi.
            'payout_msisdn' => ['required', 'regex:/^\+242[0-9]{9}$/'],
            'pin' => ['required', 'digits:6'],
        ], ['payout_msisdn.regex' => 'Numéro Mobile Money congolais attendu (+242 suivi de 9 chiffres).']);

        $transfer = $this->service->claim($request->user(), $data, $key);

        return response()->json(new IncomingTransferResource($transfer->load('provider', 'payoutProvider')), $transfer->wasRecentlyCreated ? 201 : 200);
    }

    public function index(Request $request): JsonResponse
    {
        $items = IncomingTransfer::query()->where('user_id', $request->user()->id)->with('provider', 'payoutProvider')->latest()->limit(50)->get();

        return response()->json(['data' => IncomingTransferResource::collection($items)]);
    }

    public function show(Request $request, string $id): IncomingTransferResource
    {
        return new IncomingTransferResource($this->find($request, $id)->load('provider', 'payoutProvider'));
    }

    public function refresh(Request $request, string $id): IncomingTransferResource
    {
        return new IncomingTransferResource($this->service->refresh($this->find($request, $id))->load('provider', 'payoutProvider'));
    }

    private function find(Request $request, string $id): IncomingTransfer
    {
        return IncomingTransfer::query()->where('user_id', $request->user()->id)->whereKey($id)->first()
            ?? throw new ApiException('NOT_FOUND', 'Réception introuvable.', 404);
    }
}
