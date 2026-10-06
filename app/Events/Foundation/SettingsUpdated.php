<?php

namespace App\Events\Foundation;

use App\Models\Company;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SettingsUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<string>  $keys
     */
    public function __construct(public Company $company, public array $keys) {}
}
