<?php

namespace App\Http\Controllers\Api\V1\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\StoneGradeRequest;
use App\Http\Resources\StoneGradeResource;
use App\Models\StoneGrade;
use App\Services\Masters\StoneGradeService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StoneGradeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StoneGrade::class);

        return StoneGradeResource::collection(
            StoneGrade::query()->matching(trim((string) $request->query('search', '')))->orderBy('kind')->orderBy('name')->paginate(50),
        );
    }

    public function store(StoneGradeRequest $request, StoneGradeService $grades, CompanyContext $context): StoneGradeResource
    {
        return new StoneGradeResource($grades->create($context->company(), $request->validated()));
    }

    public function show(StoneGrade $stoneGrade): StoneGradeResource
    {
        $this->authorize('view', $stoneGrade);

        return new StoneGradeResource($stoneGrade);
    }

    public function update(StoneGradeRequest $request, StoneGrade $stoneGrade, StoneGradeService $grades): StoneGradeResource
    {
        return new StoneGradeResource($grades->update($stoneGrade, $request->validated()));
    }

    public function destroy(StoneGrade $stoneGrade, StoneGradeService $grades): JsonResponse
    {
        $this->authorize('delete', $stoneGrade);
        $grades->delete($stoneGrade);

        return response()->json(['message' => 'Stone grade removed.']);
    }
}
