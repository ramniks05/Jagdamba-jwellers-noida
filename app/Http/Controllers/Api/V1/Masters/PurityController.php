<?php

namespace App\Http\Controllers\Api\V1\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\PurityRequest;
use App\Http\Resources\PurityResource;
use App\Models\MetalType;
use App\Models\Purity;
use App\Services\Masters\PurityService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurityController extends Controller
{
    public function index(Request $request, MetalType $metal): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Purity::class);

        return PurityResource::collection(
            $metal->purities()->matching(trim((string) $request->query('search', '')))->orderBy('sort_order')->orderBy('name')->paginate(20),
        );
    }

    public function store(PurityRequest $request, MetalType $metal, PurityService $purities, CompanyContext $context): PurityResource
    {
        return new PurityResource($purities->create($context->company(), $metal, $request->validated()));
    }

    public function show(MetalType $metal, Purity $purity): PurityResource
    {
        abort_unless((int) $purity->metal_type_id === (int) $metal->id, 404);
        $this->authorize('view', $purity);

        return new PurityResource($purity);
    }

    public function update(PurityRequest $request, MetalType $metal, Purity $purity, PurityService $purities): PurityResource
    {
        abort_unless((int) $purity->metal_type_id === (int) $metal->id, 404);

        return new PurityResource($purities->update($purity, $request->validated()));
    }

    public function destroy(MetalType $metal, Purity $purity, PurityService $purities): JsonResponse
    {
        abort_unless((int) $purity->metal_type_id === (int) $metal->id, 404);
        $this->authorize('delete', $purity);
        $purities->delete($purity);

        return response()->json(['message' => 'Purity removed.']);
    }
}
