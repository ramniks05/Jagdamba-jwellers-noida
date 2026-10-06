<?php

namespace App\Support;

class TenantScopeKey
{
    public const COMPANY = 'company';

    public static function forBranch(?int $branchId): string
    {
        return $branchId ? 'branch:'.$branchId : self::COMPANY;
    }
}
