<?php

namespace App\Http\Controllers\Api\V1\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\ChargeMethodRequest;
use App\Http\Resources\ChargeMethodResource;
use App\Models\ChargeMethod;
use App\Services\Masters\ChargeMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChargeMethodController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ChargeMethod::class);

        return ChargeMethodResource::collection(
            ChargeMethod::query()->orderBy('applies_to')->orderBy('sort_order')->get(),
        );
    }

    public function show(ChargeMethod $chargeMethod): ChargeMethodResource
    {
        $this->authorize('view', $chargeMethod);

        return new ChargeMethodResource($chargeMethod);
    }

    public function update(ChargeMethodRequest $request, ChargeMethod $chargeMethod, ChargeMethodService $methods): ChargeMethodResource
    {
        return new ChargeMethodResource($methods->update($chargeMethod, $request->validated()));
    }

    public function destroy(ChargeMethod $chargeMethod, ChargeMethodService $methods): JsonResponse
    {
        $this->authorize('delete', $chargeMethod);
        $methods->delete($chargeMethod);

        return response()->json(['message' => 'Calculation method removed.']);
    }
}
