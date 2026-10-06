<?php

namespace App\Services\Access;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessCatalog;
use App\Support\CompanyContext;

class AccessProvisioner
{
    public function __construct(
        private readonly AccessCatalog $catalog,
        private readonly CompanyContext $context,
    ) {}

    public function ensureCatalog(): void
    {
        foreach ($this->catalog->permissions() as $code => $meta) {
            Permission::query()->updateOrCreate(
                ['code' => $code],
                [
                    'group' => $meta['group'],
                    'name' => $meta['label'],
                ],
            );
        }

        Permission::query()
            ->whereNotIn('code', $this->catalog->permissionCodes())
            ->delete();
    }

    public function syncSystemRoles(Company $company): void
    {
        $this->context->ensureId((int) $company->id);
        $this->ensureCatalog();

        $permissionIds = Permission::query()->pluck('id', 'code');

        foreach ($this->catalog->roles() as $code => $meta) {
            $role = Role::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'code' => $code,
                ],
                [
                    'name' => $meta['name'],
                    'description' => $meta['description'],
                    'is_system' => true,
                ],
            );

            $role->forceFill([
                'name' => $meta['name'],
                'description' => $meta['description'],
                'is_system' => true,
            ])->save();

            $ids = collect($this->catalog->codesForRole($code))
                ->map(fn (string $permission) => $permissionIds[$permission] ?? null)
                ->filter()
                ->values()
                ->all();

            $role->permissions()->sync($ids);
        }
    }

    public function grant(User $user, string $roleCode): void
    {
        $this->context->ensureId((int) $user->company_id);

        $role = Role::query()->where('code', $roleCode)->first();

        if (! $role) {
            $this->syncSystemRoles($user->company);
            $role = Role::query()->where('code', $roleCode)->firstOrFail();
        }

        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->forgetPermissionCache();
    }
}
