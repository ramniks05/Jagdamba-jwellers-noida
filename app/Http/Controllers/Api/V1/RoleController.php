<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\Access\RoleService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Role::class);

        return RoleResource::collection(
            Role::query()->with('permissions')->orderBy('name')->get(),
        );
    }

    public function store(RoleRequest $request, RoleService $roles, CompanyContext $context): RoleResource
    {
        return new RoleResource($roles->create($context->company(), $request->user(), $request->validated()));
    }

    public function show(Role $role): RoleResource
    {
        $this->authorize('view', $role);

        return new RoleResource($role->load('permissions'));
    }

    public function update(RoleRequest $request, Role $role, RoleService $roles): RoleResource
    {
        return new RoleResource($roles->update($role, $request->user(), $request->validated()));
    }

    public function destroy(Role $role, RoleService $roles): JsonResponse
    {
        $this->authorize('delete', $role);
        $roles->delete($role);

        return response()->json(['message' => 'Role removed.']);
    }
}
