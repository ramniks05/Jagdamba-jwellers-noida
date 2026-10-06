<?php

namespace App\Console\Commands;

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\FinancialYear;
use App\Services\Foundation\DocumentNumberService;
use App\Support\CompanyContext;
use App\Support\TenantScopeKey;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ShopStatusCommand extends Command
{
    protected $signature = 'shop:status';

    protected $description = 'Show each shop, its current financial year, and the next invoice number';

    public function handle(CompanyContext $context, DocumentNumberService $numbers): int
    {
        $companies = $context->bypassing(fn () => Company::query()->orderBy('name')->get());

        if ($companies->isEmpty()) {
            $this->warn('No shop has been provisioned. Run php artisan db:seed');

            return self::SUCCESS;
        }

        foreach ($companies as $company) {
            $context->set($company);
            $year = FinancialYear::query()->where('is_current', true)->first();
            $sequence = DocumentSequence::query()
                ->where('document_type', DocumentType::Invoice)
                ->where('scope_key', TenantScopeKey::COMPANY)
                ->first();

            $preview = 'n/a';

            if ($sequence) {
                try {
                    $preview = $numbers->preview($sequence);
                } catch (ValidationException $exception) {
                    $preview = (string) collect($exception->errors())->flatten()->first();
                }
            }

            $this->line(sprintf(
                '%s (%s) | FY %s | next invoice %s',
                $company->name,
                $company->code,
                $year->name ?? 'none',
                $preview,
            ));
        }

        return self::SUCCESS;
    }
}
