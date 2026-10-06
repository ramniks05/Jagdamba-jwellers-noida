<?php

namespace App\Http\Controllers\Api\V1\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\DesignRequest;
use App\Http\Resources\DesignResource;
use App\Models\Design;
use App\Services\Masters\DesignService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DesignController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Design::class);

        return DesignResource::collection(
            Design::query()->with(['collection', 'category'])->matching(trim((string) $request->query('search', '')))->orderBy('name')->paginate(20),
        );
    }

    public function store(DesignRequest $request, DesignService $designs, CompanyContext $context): DesignResource
    {
        $design = $designs->create($context->company(), $request->validated());

        return new DesignResource($design->load(['collection', 'category']));
    }

    public function show(Design $design): DesignResource
    {
        $this->authorize('view', $design);

        return new DesignResource($design->load(['collection', 'category']));
    }

    public function update(DesignRequest $request, Design $design, DesignService $designs): DesignResource
    {
        return new DesignResource($designs->update($design, $request->validated())->load(['collection', 'category']));
    }

    public function destroy(Design $design, DesignService $designs): JsonResponse
    {
        $this->authorize('delete', $design);
        $designs->delete($design);

        return response()->json(['message' => 'Design removed.']);
    }
}
