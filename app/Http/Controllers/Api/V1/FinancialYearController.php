<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Foundation\FinancialYearRequest;
use App\Http\Resources\FinancialYearResource;
use App\Models\FinancialYear;
use App\Services\Foundation\FinancialYearService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FinancialYearController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FinancialYear::class);

        return FinancialYearResource::collection(
            FinancialYear::query()->orderByDesc('start_date')->paginate(20),
        );
    }

    public function store(FinancialYearRequest $request, FinancialYearService $years, CompanyContext $context): FinancialYearResource
    {
        return new FinancialYearResource($years->create($context->company(), $request->validated()));
    }

    public function show(FinancialYear $financialYear): FinancialYearResource
    {
        $this->authorize('view', $financialYear);

        return new FinancialYearResource($financialYear);
    }

    public function update(FinancialYearRequest $request, FinancialYear $financialYear, FinancialYearService $years): FinancialYearResource
    {
        return new FinancialYearResource($years->update($financialYear, $request->validated()));
    }

    public function destroy(FinancialYear $financialYear, FinancialYearService $years): JsonResponse
    {
        $this->authorize('delete', $financialYear);
        $years->delete($financialYear);

        return response()->json(['message' => 'Financial year removed.']);
    }

    public function current(FinancialYear $financialYear, FinancialYearService $years): FinancialYearResource
    {
        $this->authorize('update', $financialYear);

        return new FinancialYearResource($years->setCurrent($financialYear));
    }

    public function close(FinancialYear $financialYear, FinancialYearService $years): FinancialYearResource
    {
        $this->authorize('close', $financialYear);

        return new FinancialYearResource($years->close($financialYear, auth()->id()));
    }
}
