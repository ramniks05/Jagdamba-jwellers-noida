<?php

namespace App\Events\Access;

use Illuminate\Foundation\Events\Dispatchable;

class UserAccessChanged
{
    use Dispatchable;

    public function __construct(
        public int $companyId,
        public string $userUuid,
        public string $action,
    ) {}
}
