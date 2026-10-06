<?php

namespace App\Services\Foundation;

use App\Enums\DocumentType;
use App\Enums\SequenceResetPolicy;
use App\Events\Foundation\DocumentNumberIssued;
use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentNumberAllocation;
use App\Models\DocumentSequence;
use App\Models\FinancialYear;
use App\Support\CompanyContext;
use App\Support\TenantScopeKey;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentNumberService
{
    /**
     * @var array<string, FinancialYear|null>
     */
    private array $years = [];

    public function seedDefaults(Company $company): void
    {
        app(CompanyContext::class)->ensureId((int) $company->id);

        foreach (DocumentType::cases() as $type) {
            DocumentSequence::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'scope_key' => TenantScopeKey::COMPANY,
                    'document_type' => $type->value,
                ],
                [
                    'prefix' => $type->defaultPrefix(),
                    'separator' => config('foundation.document_sequence.separator'),
                    'padding' => config('foundation.document_sequence.padding'),
                    'next_number' => 1,
                    'reset_policy' => $type->defaultReset(),
                    'is_active' => true,
                    'is_system' => true,
                ],
            );
        }
    }

    public function for(DocumentType $type, ?Branch $branch = null): DocumentSequence
    {
        if ($branch) {
            app(CompanyContext::class)->ensureId((int) $branch->company_id);

            $override = DocumentSequence::query()
                ->where('document_type', $type)
                ->where('scope_key', TenantScopeKey::forBranch($branch->id))
                ->first();

            if ($override) {
                return $override;
            }
        }

        return DocumentSequence::query()
            ->where('document_type', $type)
            ->where('scope_key', TenantScopeKey::COMPANY)
            ->firstOrFail();
    }

    public function preview(DocumentSequence $sequence, ?CarbonInterface $on = null): string
    {
        app(CompanyContext::class)->ensureId((int) $sequence->company_id);
        $on = Carbon::parse($on ?? now());
        $numeric = $this->numberFor($sequence, $this->periodKey($sequence, $on));

        return $this->format($sequence, $numeric, $on);
    }

    public function issue(DocumentSequence $sequence, ?CarbonInterface $on = null): DocumentNumberAllocation
    {
        app(CompanyContext::class)->ensureId((int) $sequence->company_id);
        $on = Carbon::parse($on ?? now());

        return DB::transaction(function () use ($sequence, $on) {
            $locked = DocumentSequence::query()->whereKey($sequence->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->is_active) {
                throw ValidationException::withMessages([
                    'sequence' => 'This number series is inactive.',
                ]);
            }

            $periodKey = $this->periodKey($locked, $on);
            $numeric = $this->numberFor($locked, $periodKey);
            $formatted = $this->format($locked, $numeric, $on);
            $this->assertFits($formatted);

            $allocation = DocumentNumberAllocation::query()->create([
                'company_id' => $locked->company_id,
                'document_sequence_id' => $locked->id,
                'document_type' => $locked->document_type,
                'number' => $formatted,
                'issued_at' => $on,
                'issued_by' => auth()->id(),
            ]);

            $locked->forceFill([
                'next_number' => $numeric + 1,
                'last_period_key' => $periodKey,
                'last_issued_number' => $formatted,
                'last_issued_at' => $on,
                'financial_year_id' => $locked->reset_policy === SequenceResetPolicy::FinancialYear
                    ? $this->requireYear($locked, $on)->id
                    : $locked->financial_year_id,
            ])->save();

            DB::afterCommit(fn () => event(new DocumentNumberIssued($allocation)));

            return $allocation;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(DocumentSequence $sequence, array $attributes): DocumentSequence
    {
        return DB::transaction(function () use ($sequence, $attributes) {
            app(CompanyContext::class)->ensureId((int) $sequence->company_id);
            $locked = DocumentSequence::query()->whereKey($sequence->getKey())->lockForUpdate()->firstOrFail();
            $next = (int) $attributes['next_number'];

            if ($next < 1) {
                throw ValidationException::withMessages([
                    'next_number' => 'The next number must be at least 1.',
                ]);
            }

            if ($locked->last_issued_at && $next < (int) $locked->next_number) {
                throw ValidationException::withMessages([
                    'next_number' => 'The next number cannot be lower than '.$locked->next_number.' after a number has been issued.',
                ]);
            }

            $locked->fill([
                'prefix' => $attributes['prefix'],
                'suffix' => $attributes['suffix'] ?: null,
                'separator' => $attributes['separator'],
                'padding' => (int) $attributes['padding'],
                'next_number' => $next,
                'reset_policy' => $attributes['reset_policy'],
                'is_active' => filter_var($attributes['is_active'], FILTER_VALIDATE_BOOLEAN),
            ]);

            $this->assertFits($this->preview($locked));
            $locked->save();

            return $locked->refresh();
        });
    }

    private function numberFor(DocumentSequence $sequence, string $periodKey): int
    {
        if (
            $sequence->reset_policy !== SequenceResetPolicy::Never
            && $sequence->last_period_key !== null
            && $sequence->last_period_key !== $periodKey
        ) {
            return 1;
        }

        return (int) $sequence->next_number;
    }

    private function periodKey(DocumentSequence $sequence, CarbonInterface $on): string
    {
        return match ($sequence->reset_policy) {
            SequenceResetPolicy::Never => 'all',
            SequenceResetPolicy::Yearly => 'year:'.$on->format('Y'),
            SequenceResetPolicy::Monthly => 'month:'.$on->format('Y-m'),
            SequenceResetPolicy::FinancialYear => 'fy:'.$this->requireYear($sequence, $on)->id,
        };
    }

    private function format(DocumentSequence $sequence, int $number, CarbonInterface $on): string
    {
        $year = $sequence->reset_policy === SequenceResetPolicy::FinancialYear
            ? $this->requireYear($sequence, $on)
            : null;
        $parts = [strtoupper((string) $sequence->prefix)];
        $token = match ($sequence->reset_policy) {
            SequenceResetPolicy::Never => null,
            SequenceResetPolicy::Yearly => $on->format('Y'),
            SequenceResetPolicy::Monthly => $on->format('Ym'),
            SequenceResetPolicy::FinancialYear => $year?->name,
        };

        if ($token) {
            $parts[] = $token;
        }

        $parts[] = str_pad((string) $number, max(1, (int) $sequence->padding), '0', STR_PAD_LEFT);
        $formatted = implode((string) $sequence->separator, $parts);

        if ($sequence->suffix) {
            $formatted .= $sequence->separator.strtoupper((string) $sequence->suffix);
        }

        return $formatted;
    }

    private function requireYear(DocumentSequence $sequence, CarbonInterface $on): FinancialYear
    {
        $year = $this->yearCovering((int) $sequence->company_id, $on);

        if (! $year) {
            throw ValidationException::withMessages([
                'sequence' => 'No financial year covers '.$on->toDateString().'. Open a financial year before using this series.',
            ]);
        }

        return $year;
    }

    private function yearCovering(int $companyId, CarbonInterface $on): ?FinancialYear
    {
        $key = $companyId.'|'.$on->toDateString();

        if (! array_key_exists($key, $this->years)) {
            $this->years[$key] = FinancialYear::query()
                ->where('company_id', $companyId)
                ->whereDate('start_date', '<=', $on->toDateString())
                ->whereDate('end_date', '>=', $on->toDateString())
                ->first();
        }

        return $this->years[$key];
    }

    private function assertFits(string $number): void
    {
        if (strlen($number) > 80) {
            throw ValidationException::withMessages([
                'prefix' => 'The generated number is too long. Shorten the prefix, suffix, or padding.',
            ]);
        }
    }
}
