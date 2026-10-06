<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\UserRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\Access\UserService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request, CompanyContext $context): View
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
            ->paginate(15)
            ->withQueryString();

        return view('access.users.index', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('access.users.form', $this->formData(new User(['is_active' => true])));
    }

    public function store(UserRequest $request, UserService $users, CompanyContext $context): RedirectResponse
    {
        $users->create($context->company(), $request->user(), $request->validated());

        return redirect()->route('users.index')->with('status', 'User saved.');
    }

    public function edit(User $user): View
    {
        $this->authorize('view', $user);

        return view('access.users.form', $this->formData($user->load(['roles', 'branches'])));
    }

    public function update(UserRequest $request, User $user, UserService $users): RedirectResponse
    {
        $users->update($user, $request->user(), $request->validated());

        return redirect()->route('users.index')->with('status', 'User saved.');
    }

    public function destroy(User $user, UserService $users): RedirectResponse
    {
        $this->authorize('delete', $user);
        $users->delete($user, auth()->user());

        return redirect()->route('users.index')->with('status', 'User removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => Role::query()->orderBy('name')->get(),
            'branches' => Branch::query()->orderByDesc('is_head_office')->orderBy('name')->get(),
            'selectedRoles' => old('roles', $user->relationLoaded('roles') ? $user->roles->pluck('uuid')->all() : []),
            'selectedBranches' => old('branches', $user->relationLoaded('branches') ? $user->branches->pluck('uuid')->all() : []),
            'canSave' => $user->exists ? auth()->user()->can('update', $user) : auth()->user()->can('create', User::class),
        ];
    }
}
