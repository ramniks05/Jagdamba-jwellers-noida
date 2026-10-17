<?php

use App\Models\Company;
use App\Models\Purity;
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
        Purity::withoutGlobalScopes()
            ->where('code', 'OTHERS')
            ->get()
            ->each(function (Purity $purity): void {
                if ($purity->deletionBlocker() === null) {
                    $purity->delete();
                }
            });
    }
};
