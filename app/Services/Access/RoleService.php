<?php

namespace App\Services\Access;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessCatalog;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoleService
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly AccessCatalog $catalog,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, User $actor, array $attributes): Role
    {
        return DB::transaction(function () use ($company, $actor, $attributes) {
            $this->context->ensureId((int) $company->id);
            $codes = $this->permissionCodes($actor, $attributes['permissions']);
            $code = $this->codeFromName($attributes['name']);

            if (array_key_exists($code, $this->catalog->roles()) || Role::withTrashed()->where('code', $code)->exists()) {
                throw ValidationException::withMessages([
                    'name' => 'This name is reserved or already in use.',
                ]);
            }

            $role = new Role([
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'is_system' => false,
            ]);
            $role->company_id = $company->id;
            $role->code = $code;
            $role->save();
            $this->syncPermissions($role, $codes);

            return $role->load('permissions');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Role $role, User $actor, array $attributes): Role
    {
        return DB::transaction(function () use ($role, $actor, $attributes) {
            $this->context->ensureId((int) $role->company_id);
            $this->guardSystem($role);

            $codes = $this->permissionCodes($actor, $attributes['permissions']);
            $role->fill([
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
            ])->save();
            $this->syncPermissions($role, $codes);

            return $role->load('permissions');
        });
    }

    public function delete(Role $role): void
    {
        DB::transaction(function () use ($role) {
            $this->context->ensureId((int) $role->company_id);
            $this->guardSystem($role);

            if ($role->users()->exists()) {
                throw ValidationException::withMessages([
                    'role' => 'Remove this role from its users before deleting it.',
                ]);
            }

            $role->delete();
        });
    }

    private function guardSystem(Role $role): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages([
                'role' => 'Locked roles cannot be changed.',
            ]);
        }
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
    private function permissionCodes(User $actor, array $codes): array
    {
        $codes = array_values(array_unique($codes));
        $unknown = array_diff($codes, $this->catalog->permissionCodes());

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'One or more permissions are not available.',
            ]);
        }

        if ($this->isAdministrator($actor)) {
            return $codes;
        }

        $extra = array_diff($codes, $actor->permissionCodes());

        if ($extra !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'You cannot grant a permission you do not have.',
            ]);
        }

        return $codes;
    }

    /**
     * @param  array<int, string>  $codes
     */
    private function syncPermissions(Role $role, array $codes): void
    {
        $ids = Permission::query()->whereIn('code', $codes)->pluck('id');
        $role->permissions()->sync($ids);
    }

    private function codeFromName(string $name): string
    {
        $code = (string) Str::of($name)->lower()->squish()->replace(' ', '_')->replaceMatches('/[^a-z0-9_]+/', '')->trim('_');

        if ($code === '' || strlen($code) > 50) {
            throw ValidationException::withMessages([
                'name' => 'Enter a role name that can be saved.',
            ]);
        }

        return $code;
    }

    private function isAdministrator(User $user): bool
    {
        $user->loadMissing('roles');

        return $user->roles->contains(
            fn (Role $role) => in_array($role->code, $this->catalog->administratorCodes(), true),
        );
    }
}
