<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Access\UserService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(Request $request, CompanyContext $context): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->query('search', ''));
        $like = '%'.addcslashes($search, '%_\\').'%';

        $users = User::query()
            ->where('company_id', $context->id())
            ->with(['roles', 'branches'])
            ->when($search !== '', function ($query) use ($like) {
                $query->where(function ($query) use ($like) {
                    $query->where('name', 'like', $like)->orWhere('email', 'like', $like);
                });
            })
            ->orderBy('name')
            ->paginate(15);

        return UserResource::collection($users);
    }

    public function store(UserRequest $request, UserService $users, CompanyContext $context): UserResource
    {
        $user = $users->create($context->company(), $request->user(), $request->validated());

        return new UserResource($user);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load(['roles', 'branches']));
    }

    public function update(UserRequest $request, User $user, UserService $users): UserResource
    {
        return new UserResource($users->update($user, $request->user(), $request->validated()));
    }

    public function destroy(User $user, UserService $users): JsonResponse
    {
        $this->authorize('delete', $user);
        $users->delete($user, auth()->user());

        return response()->json(['message' => 'User removed.']);
    }
}
