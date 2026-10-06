<?php

namespace App\Http\Controllers\Api\V1\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\CatalogRequest;
use App\Http\Resources\CatalogResource;
use App\Services\Masters\MasterRecordService;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

abstract class SimpleCatalogController extends Controller
{
    abstract public static function modelClass(): string;

    abstract public function routeKey(): string;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', static::modelClass());
        $class = static::modelClass();

        $records = $class::query()
            ->matching(trim((string) $request->query('search', '')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return CatalogResource::collection($records);
    }

    public function store(CatalogRequest $request, MasterRecordService $records, CompanyContext $context): CatalogResource
    {
        return new CatalogResource($records->create(static::modelClass(), $context->company(), $request->validated()));
    }

    public function show(Request $request): CatalogResource
    {
        $record = $this->find($request);
        $this->authorize('view', $record);

        return new CatalogResource($record);
    }

    public function update(CatalogRequest $request, MasterRecordService $records): CatalogResource
    {
        return new CatalogResource($records->update($this->find($request), $request->validated()));
    }

    public function destroy(Request $request, MasterRecordService $records): JsonResponse
    {
        $record = $this->find($request);
        $this->authorize('delete', $record);
        $records->delete($record);

        return response()->json(['message' => 'Removed.']);
    }

    protected function find(Request $request): Model
    {
        $class = static::modelClass();

        return $class::query()->where('uuid', $request->route($this->routeKey()))->firstOrFail();
    }
}
