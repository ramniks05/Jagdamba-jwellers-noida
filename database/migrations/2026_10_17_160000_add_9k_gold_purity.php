<?php

use App\Models\Company;
use App\Services\Masters\MasterProvisioner;
use App\Support\CompanyContext;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $context = app(CompanyContext::class);
        $masters = app(MasterProvisioner::class);

        foreach (Company::query()->orderBy('id')->get() as $company) {
            $context->set($company);
            $masters->seed($company);
        }
    }

    public function down(): void
    {
        App\Models\Purity::withoutGlobalScopes()
            ->where('code', '9K')
            ->get()
            ->each(function (App\Models\Purity $purity): void {
                if ($purity->deletionBlocker() === null) {
                    $purity->delete();
                }
            });
    }
};
