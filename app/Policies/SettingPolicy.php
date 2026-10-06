<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;
use App\Policies\Concerns\ManagesCompanyRecords;

class SettingPolicy
{
    use ManagesCompanyRecords;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'settings.view');
    }

    public function update(User $user, ?Setting $setting = null): bool
    {
        if ($setting) {
            return $this->allowsCompany($user, (int) $setting->company_id, 'settings.manage');
        }

        return $this->allows($user, 'settings.manage');
    }
}
