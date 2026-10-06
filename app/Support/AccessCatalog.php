<?php

namespace App\Support;

use RuntimeException;

class AccessCatalog
{
    /**
     * @return array<string, array{group: string, label: string}>
     */
    public function permissions(): array
    {
        return config('access.permissions');
    }

    /**
     * @return array<int, string>
     */
    public function permissionCodes(): array
    {
        return array_keys($this->permissions());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function roles(): array
    {
        return config('access.roles');
    }

    /**
     * @return array<int, string>
     */
    public function administratorCodes(): array
    {
        return ['owner', 'super_admin'];
    }

    /**
     * @return array<int, string>
     */
    public function codesForRole(string $roleCode): array
    {
        $roles = $this->roles();

        if (! isset($roles[$roleCode])) {
            throw new RuntimeException('Unknown role ['.$roleCode.'].');
        }

        $role = $roles[$roleCode];
        $all = $this->permissionCodes();
        $permissions = $role['permissions'];

        if ($permissions === '*') {
            return $all;
        }

        if ($permissions === 'read') {
            return array_values(array_filter(
                $all,
                fn (string $code) => preg_match('/\.(view|export|print)$/', $code) === 1,
            ));
        }

        if ($permissions === 'except') {
            return array_values(array_diff($all, $role['except'] ?? []));
        }

        return array_values($permissions);
    }
}
