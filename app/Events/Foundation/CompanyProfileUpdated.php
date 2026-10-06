<?php

namespace App\Events\Foundation;

use App\Models\Company;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CompanyProfileUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Company $company) {}
}
