<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Access\RoleService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('access.roles.index', [
            'roles' => Role::query()->withCount('users')->orderByDesc('is_system')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('access.roles.form', $this->formData(new Role));
    }

    public function store(RoleRequest $request, RoleService $roles, CompanyContext $context): RedirectResponse
    {
        $roles->create($context->company(), $request->user(), $request->validated());

        return redirect()->route('roles.index')->with('status', 'Role saved.');
    }

    public function edit(Role $role): View
    {
        $this->authorize('view', $role);

        return view('access.roles.form', $this->formData($role->load('permissions')));
    }

    public function update(RoleRequest $request, Role $role, RoleService $roles): RedirectResponse
    {
        $roles->update($role, $request->user(), $request->validated());

        return redirect()->route('roles.index')->with('status', 'Role saved.');
    }

    public function destroy(Role $role, RoleService $roles): RedirectResponse
    {
        $this->authorize('delete', $role);
        $roles->delete($role);

        return redirect()->route('roles.index')->with('status', 'Role removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Role $role): array
    {
        return [
            'role' => $role,
            'groups' => Permission::query()->orderBy('name')->get()->groupBy('group'),
            'groupLabels' => config('access.groups'),
            'selected' => old('permissions', $role->relationLoaded('permissions') ? $role->permissions->pluck('code')->all() : []),
            'canSave' => $role->exists ? auth()->user()->can('update', $role) : auth()->user()->can('create', Role::class),
        ];
    }
}
