<?php

namespace App\Services\Access;

use App\Events\Access\UserAccessChanged;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessCatalog;
use App\Support\CompanyContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly AccessCatalog $catalog,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, User $actor, array $attributes): User
    {
        return DB::transaction(function () use ($company, $actor, $attributes) {
            $this->context->ensureId((int) $company->id);

            $roles = $this->rolesFor($attributes['roles']);
            $branches = $this->branchesFor($attributes['branches'] ?? []);
            $active = $this->flag($attributes['is_active'] ?? true);

            $this->assertActorMayAssign($actor, $roles, $branches, null);

            $user = new User([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'phone' => $attributes['phone'] ?? null,
                'password' => $attributes['password'],
            ]);
            $user->company_id = $company->id;
            $user->is_active = $active;
            $user->email_verified_at = now();
            $user->save();

            $user->roles()->sync($roles->pluck('id')->all());
            $user->branches()->sync($branches->pluck('id')->all());
            $user->forgetPermissionCache();

            $this->assertAdministratorRemains($company, $user, $active && $this->rolesIncludeAdministrator($roles));
            $this->record($user, 'created');

            return $user->load(['roles', 'branches']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, User $actor, array $attributes): User
    {
        return DB::transaction(function () use ($user, $actor, $attributes) {
            $company = $this->companyOf($user);
            $roles = $this->rolesFor($attributes['roles']);
            $branches = $this->branchesFor($attributes['branches'] ?? []);
            $active = $this->flag($attributes['is_active'] ?? false);

            if ($actor->is($user) && ! $active) {
                throw ValidationException::withMessages([
                    'is_active' => 'You cannot deactivate your own account.',
                ]);
            }

            $this->assertActorMayAssign($actor, $roles, $branches, $user);

            $user->fill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'phone' => $attributes['phone'] ?? null,
            ]);
            $user->is_active = $active;

            if (! empty($attributes['password'])) {
                $user->password = $attributes['password'];
            }

            $user->save();
            $user->roles()->sync($roles->pluck('id')->all());
            $user->branches()->sync($branches->pluck('id')->all());
            $user->forgetPermissionCache();

            if (! $active || ! empty($attributes['password'])) {
                $this->revokeAccess($user);
            }

            $this->assertAdministratorRemains($company, $user, $active && $this->rolesIncludeAdministrator($roles));
            $this->record($user, 'updated');

            return $user->load(['roles', 'branches']);
        });
    }

    public function delete(User $user, User $actor): void
    {
        DB::transaction(function () use ($user, $actor) {
            $company = $this->companyOf($user);

            if ($actor->is($user)) {
                throw ValidationException::withMessages([
                    'user' => 'You cannot remove your own account.',
                ]);
            }

            $this->assertActorMayAssign($actor, $user->roles()->get(), null, $user);
            $this->assertAdministratorRemains($company, $user, false);
            $this->revokeAccess($user);
            $this->record($user, 'deleted');
            $user->delete();
        });
    }

    private function companyOf(User $user): Company
    {
        $this->context->ensureId((int) $user->company_id);

        return $user->company;
    }

    /**
     * @param  array<int, string>  $uuids
     * @return Collection<int, Role>
     */
    private function rolesFor(array $uuids): Collection
    {
        $uuids = array_values(array_unique($uuids));
        $roles = Role::query()->whereIn('uuid', $uuids)->get();

        if ($roles->count() !== count($uuids)) {
            throw ValidationException::withMessages([
                'roles' => 'One or more roles are not available for this shop.',
            ]);
        }

        return $roles;
    }

    /**
     * @param  array<int, string>  $uuids
     * @return Collection<int, Branch>
     */
    private function branchesFor(array $uuids): Collection
    {
        $uuids = array_values(array_unique($uuids));

        if ($uuids === []) {
            return collect();
        }

        $branches = Branch::query()->whereIn('uuid', $uuids)->get();

        if ($branches->count() !== count($uuids)) {
            throw ValidationException::withMessages([
                'branches' => 'One or more branches are not available for this shop.',
            ]);
        }

        return $branches;
    }

    /**
     * @param  Collection<int, Role>  $roles
     * @param  Collection<int, Branch>|null  $branches
     */
    private function assertActorMayAssign(User $actor, Collection $roles, ?Collection $branches, ?User $target): void
    {
        if ($this->isAdministrator($actor)) {
            return;
        }

        if ($target && ($this->isAdministrator($target) || $this->rolesExceedActor($actor, $target->roles()->with('permissions')->get()))) {
            throw ValidationException::withMessages([
                'roles' => 'You cannot change a user with broader access than your own.',
            ]);
        }

        if ($this->rolesExceedActor($actor, $roles->load('permissions'))) {
            throw ValidationException::withMessages([
                'roles' => 'You cannot assign a role with broader access than your own.',
            ]);
        }

        if ($branches === null) {
            return;
        }

        $restricted = $actor->restrictedBranchIds();

        if ($restricted === null) {
            return;
        }

        if ($branches->isEmpty()) {
            throw ValidationException::withMessages([
                'branches' => 'Select at least one branch you can access.',
            ]);
        }

        if ($branches->contains(fn (Branch $branch) => ! in_array((int) $branch->id, $restricted, true))) {
            throw ValidationException::withMessages([
                'branches' => 'You can only assign branches you can access.',
            ]);
        }
    }

    /**
     * @param  Collection<int, Role>  $roles
     */
    private function rolesExceedActor(User $actor, Collection $roles): bool
    {
        $allowed = $actor->permissionCodes();
        $wanted = $roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('code'))
            ->unique();

        return $wanted->contains(fn (string $code) => ! in_array($code, $allowed, true));
    }

    private function assertAdministratorRemains(Company $company, User $subject, bool $subjectRemainsAdmin): void
    {
        if ($subjectRemainsAdmin) {
            return;
        }

        $others = User::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereKeyNot($subject->id)
            ->whereHas('roles', function ($query) {
                $query->whereIn('code', $this->catalog->administratorCodes());
            })
            ->exists();

        if (! $others) {
            throw ValidationException::withMessages([
                'roles' => 'The shop must keep at least one active Owner or Super Admin.',
            ]);
        }
    }

    /**
     * @param  Collection<int, Role>  $roles
     */
    private function rolesIncludeAdministrator(Collection $roles): bool
    {
        return $roles->contains(fn (Role $role) => in_array($role->code, $this->catalog->administratorCodes(), true));
    }

    private function isAdministrator(User $user): bool
    {
        $user->loadMissing('roles');

        return $user->roles->contains(
            fn (Role $role) => in_array($role->code, $this->catalog->administratorCodes(), true),
        );
    }

    private function revokeAccess(User $user): void
    {
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function record(User $user, string $action): void
    {
        DB::afterCommit(function () use ($user, $action) {
            UserAccessChanged::dispatch((int) $user->company_id, (string) $user->uuid, $action);
        });
    }

    private function flag(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
