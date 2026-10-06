<?php

namespace App\Support;

class IdentityRules
{
    public const GSTIN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    public const PAN = '/^[A-Z]{5}[0-9]{4}[A-Z]$/';

    public const CODE = '/^[A-Z0-9]{2,20}$/';
}
