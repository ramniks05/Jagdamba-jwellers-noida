<?php

namespace App\Services\Foundation;

use App\Enums\BranchStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, array $attributes): Branch
    {
        return DB::transaction(function () use ($company, $attributes) {
            app(CompanyContext::class)->ensureId((int) $company->id);
            $branch = new Branch($this->attributes($attributes));
            $branch->company_id = $company->id;
            $branch->status = BranchStatus::from($attributes['status']);
            $branch->is_head_office = false;
            $branch->save();

            if ($this->flag($attributes['is_head_office'] ?? false)) {
                $this->assignHeadOffice($branch);
            }

            return $branch->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Branch $branch, array $attributes): Branch
    {
        return DB::transaction(function () use ($branch, $attributes) {
            app(CompanyContext::class)->ensureId((int) $branch->company_id);
            $wantsHead = $this->flag($attributes['is_head_office'] ?? false);
            $status = BranchStatus::from($attributes['status']);

            if ($branch->is_head_office && ! $wantsHead) {
                throw ValidationException::withMessages([
                    'is_head_office' => 'Assign another branch as head office before removing this one.',
                ]);
            }

            if (($branch->is_head_office || $wantsHead) && $status !== BranchStatus::Active) {
                throw ValidationException::withMessages([
                    'status' => 'The head office must stay active.',
                ]);
            }

            $branch->fill($this->attributes($attributes));
            $branch->status = $status;
            $branch->save();

            if ($wantsHead) {
                $this->assignHeadOffice($branch);
            }

            return $branch->refresh();
        });
    }

    public function delete(Branch $branch): void
    {
        app(CompanyContext::class)->ensureId((int) $branch->company_id);

        if ($branch->is_head_office) {
            throw ValidationException::withMessages([
                'branch' => 'The head office branch cannot be removed.',
            ]);
        }

        $branch->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function attributes(array $attributes): array
    {
        $data = Arr::only($attributes, [
            'code',
            'name',
            'email',
            'phone',
            'mobile',
            'gstin',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
            'timezone',
        ]);

        foreach (['code', 'gstin'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = strtoupper($data[$field]);
            }
        }

        return $data;
    }

    private function assignHeadOffice(Branch $branch): void
    {
        if ($branch->status !== BranchStatus::Active) {
            throw ValidationException::withMessages([
                'status' => 'The head office must stay active.',
            ]);
        }

        Branch::query()
            ->where('company_id', $branch->company_id)
            ->whereKeyNot($branch->id)
            ->update(['is_head_office' => false]);

        $branch->is_head_office = true;
        $branch->save();
    }

    private function flag(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
