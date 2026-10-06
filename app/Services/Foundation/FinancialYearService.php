<?php

namespace App\Services\Foundation;

use App\Models\Company;
use App\Models\FinancialYear;
use App\Support\CompanyContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialYearService
{
    /**
     * @return array{name: string, start: string, end: string}
     */
    public function suggestRange(Company $company, ?CarbonInterface $on = null): array
    {
        $on = Carbon::parse($on ?? now())->startOfDay();
        $startMonth = max(1, min(12, (int) $company->fy_start_month));
        $start = Carbon::create($on->year, $startMonth, 1)->startOfDay();

        if ($on->lt($start)) {
            $start->subYear();
        }

        $end = $start->copy()->addYear()->subDay();
        $name = $start->year === $end->year
            ? $start->format('Y')
            : $start->format('Y').'-'.$end->format('y');

        return [
            'name' => $name,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ];
    }

    public function openInitialYear(Company $company, ?CarbonInterface $on = null): FinancialYear
    {
        app(CompanyContext::class)->ensureId((int) $company->id);

        $current = FinancialYear::query()
            ->where('company_id', $company->id)
            ->where('is_current', true)
            ->first();

        if ($current) {
            return $current;
        }

        $range = $this->suggestRange($company, $on);

        return $this->create($company, [
            'name' => $range['name'],
            'start_date' => $range['start'],
            'end_date' => $range['end'],
            'is_current' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, array $attributes): FinancialYear
    {
        return DB::transaction(function () use ($company, $attributes) {
            app(CompanyContext::class)->ensureId((int) $company->id);
            [$start, $end] = $this->dates($attributes);
            $this->assertNoOverlap((int) $company->id, $start, $end);
            $this->assertUniqueName((int) $company->id, (string) $attributes['name']);

            $isFirst = ! FinancialYear::query()->where('company_id', $company->id)->exists();
            $year = FinancialYear::query()->create([
                'company_id' => $company->id,
                'name' => $attributes['name'],
                'start_date' => $start,
                'end_date' => $end,
                'is_current' => false,
                'is_closed' => false,
            ]);

            if ($isFirst || $this->flag($attributes['is_current'] ?? false)) {
                $this->markCurrent($year);
            }

            return $year->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(FinancialYear $year, array $attributes): FinancialYear
    {
        app(CompanyContext::class)->ensureId((int) $year->company_id);

        if ($year->is_closed) {
            throw ValidationException::withMessages([
                'name' => 'A closed financial year cannot be edited.',
            ]);
        }

        return DB::transaction(function () use ($year, $attributes) {
            [$start, $end] = $this->dates($attributes);
            $this->assertNoOverlap((int) $year->company_id, $start, $end, (int) $year->id);
            $this->assertUniqueName((int) $year->company_id, (string) $attributes['name'], (int) $year->id);

            $year->fill([
                'name' => $attributes['name'],
                'start_date' => $start,
                'end_date' => $end,
            ])->save();

            if ($this->flag($attributes['is_current'] ?? false)) {
                $this->markCurrent($year);
            }

            return $year->refresh();
        });
    }

    public function setCurrent(FinancialYear $year): FinancialYear
    {
        app(CompanyContext::class)->ensureId((int) $year->company_id);

        return DB::transaction(function () use ($year) {
            $this->markCurrent($year);

            return $year->refresh();
        });
    }

    public function close(FinancialYear $year, ?int $userId): FinancialYear
    {
        app(CompanyContext::class)->ensureId((int) $year->company_id);

        if ($year->is_current) {
            throw ValidationException::withMessages([
                'year' => 'Set another financial year as current before closing this one.',
            ]);
        }

        if ($year->is_closed) {
            throw ValidationException::withMessages([
                'year' => 'This financial year is already closed.',
            ]);
        }

        $year->forceFill([
            'is_closed' => true,
            'closed_at' => now(),
            'closed_by' => $userId,
        ])->save();

        return $year->refresh();
    }

    public function delete(FinancialYear $year): void
    {
        app(CompanyContext::class)->ensureId((int) $year->company_id);

        if ($year->is_current) {
            throw ValidationException::withMessages([
                'year' => 'The current financial year cannot be removed.',
            ]);
        }

        if ($year->is_closed) {
            throw ValidationException::withMessages([
                'year' => 'A closed financial year cannot be removed.',
            ]);
        }

        $year->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: string, 1: string}
     */
    private function dates(array $attributes): array
    {
        $start = Carbon::parse($attributes['start_date'])->toDateString();
        $end = Carbon::parse($attributes['end_date'])->toDateString();

        if ($end <= $start) {
            throw ValidationException::withMessages([
                'end_date' => 'The end date must be after the start date.',
            ]);
        }

        return [$start, $end];
    }

    private function assertNoOverlap(int $companyId, string $start, string $end, ?int $ignoreId = null): void
    {
        $overlap = FinancialYear::query()
            ->where('company_id', $companyId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => 'This financial year overlaps an existing year.',
            ]);
        }
    }

    private function assertUniqueName(int $companyId, string $name, ?int $ignoreId = null): void
    {
        $exists = FinancialYear::query()
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'A financial year with this name already exists.',
            ]);
        }
    }

    private function markCurrent(FinancialYear $year): void
    {
        if ($year->is_closed) {
            throw ValidationException::withMessages([
                'year' => 'A closed financial year cannot be the current year.',
            ]);
        }

        FinancialYear::query()
            ->where('company_id', $year->company_id)
            ->whereKeyNot($year->id)
            ->update(['is_current' => false]);

        $year->is_current = true;
        $year->save();
    }

    private function flag(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
