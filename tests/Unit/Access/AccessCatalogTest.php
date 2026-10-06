<?php

namespace Tests\Unit\Access;

use App\Support\AccessCatalog;
use Tests\TestCase;

class AccessCatalogTest extends TestCase
{
    public function test_default_roles_only_use_known_permissions(): void
    {
        $catalog = app(AccessCatalog::class);
        $codes = $catalog->permissionCodes();

        foreach (array_keys($catalog->roles()) as $role) {
            foreach ($catalog->codesForRole($role) as $permission) {
                $this->assertContains($permission, $codes);
            }
        }

        $this->assertEqualsCanonicalizing($codes, $catalog->codesForRole('owner'));
        $this->assertEqualsCanonicalizing($codes, $catalog->codesForRole('super_admin'));
        $this->assertContains('sales.create', $catalog->codesForRole('cashier'));
        $this->assertContains('masters.view', $catalog->codesForRole('cashier'));
        $this->assertNotContains('masters.create', $catalog->codesForRole('cashier'));
        $this->assertNotContains('settings.manage', $catalog->codesForRole('cashier'));
        $this->assertNotContains('users.delete', $catalog->codesForRole('manager'));
        $this->assertNotContains('masters.delete', $catalog->codesForRole('manager'));
        $this->assertContains('masters.update', $catalog->codesForRole('manager'));
        $this->assertContains('users.create', $catalog->codesForRole('manager'));
        $this->assertContains('reports.view', $catalog->codesForRole('auditor'));
        $this->assertNotContains('users.create', $catalog->codesForRole('auditor'));
        $this->assertNotContains('financial_years.close', $catalog->codesForRole('auditor'));
    }
}
