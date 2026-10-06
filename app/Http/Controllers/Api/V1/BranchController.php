<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Foundation\BranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Services\Foundation\BranchService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Branch::class);

        $branches = Branch::query()->visibleTo(request()->user())->orderByDesc('is_head_office')->orderBy('name')->paginate(15);

        return BranchResource::collection($branches);
    }

    public function store(BranchRequest $request, BranchService $branches, CompanyContext $context): BranchResource
    {
        $branch = $branches->create($context->company(), $request->validated());

        return new BranchResource($branch);
    }

    public function show(Branch $branch): BranchResource
    {
        $this->authorize('view', $branch);

        return new BranchResource($branch);
    }

    public function update(BranchRequest $request, Branch $branch, BranchService $branches): BranchResource
    {
        return new BranchResource($branches->update($branch, $request->validated()));
    }

    public function destroy(Branch $branch, BranchService $branches): JsonResponse
    {
        $this->authorize('delete', $branch);
        $branches->delete($branch);

        return response()->json(['message' => 'Branch removed.']);
    }
}
