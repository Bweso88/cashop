<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payments\Models\Beneficiary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfers\BeneficiaryRequest;
use App\Http\Resources\Transfers\BeneficiaryResource;
use App\Support\Http\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BeneficiaryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Beneficiary::query()->where('user_id', $request->user()->id)->orderByDesc('last_used_at')->orderByDesc('created_at')->get();

        return response()->json(['data' => BeneficiaryResource::collection($items)]);
    }

    public function store(BeneficiaryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $hash = Beneficiary::identityHash($data['first_name'], $data['last_name'], $data['country'], $data['phone'] ?? null);
        $userId = $request->user()->id;

        if ($existing = Beneficiary::query()->where('user_id', $userId)->where('identity_hash', $hash)->first()) {
            return response()->json(new BeneficiaryResource($existing));
        }

        $beneficiary = Beneficiary::create([
            'user_id' => $userId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone_e164' => $data['phone'] ?? null,
            'country_code' => $data['country'],
            'city' => $data['city'] ?? null,
            'relationship' => $data['relationship'] ?? null,
            'details' => $data['details'] ?? null,
            'identity_hash' => $hash,
            'display_name' => Beneficiary::displayNameFor($data['first_name'], $data['last_name']),
        ]);

        return response()->json(new BeneficiaryResource($beneficiary), 201);
    }

    public function show(Request $request, string $id): BeneficiaryResource
    {
        return new BeneficiaryResource($this->find($request, $id));
    }

    public function update(BeneficiaryRequest $request, string $id): BeneficiaryResource
    {
        $beneficiary = $this->find($request, $id);
        $data = $request->validated();
        $beneficiary->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone_e164' => $data['phone'] ?? null,
            'country_code' => $data['country'],
            'city' => $data['city'] ?? null,
            'relationship' => $data['relationship'] ?? null,
            'details' => $data['details'] ?? null,
            'identity_hash' => Beneficiary::identityHash($data['first_name'], $data['last_name'], $data['country'], $data['phone'] ?? null),
            'display_name' => Beneficiary::displayNameFor($data['first_name'], $data['last_name']),
        ]);

        return new BeneficiaryResource($beneficiary);
    }

    public function destroy(Request $request, string $id): Response
    {
        // Suppression logique : les transferts passés gardent leur bénéficiaire.
        $this->find($request, $id)->delete();

        return response()->noContent();
    }

    /** Un bénéficiaire d'un autre utilisateur est introuvable (404), sans révéler son existence. */
    private function find(Request $request, string $id): Beneficiary
    {
        return Beneficiary::query()->where('user_id', $request->user()->id)->whereKey($id)->first()
            ?? throw new ApiException('NOT_FOUND', 'Bénéficiaire introuvable.', 404);
    }
}
