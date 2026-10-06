<?php

namespace App\Services\Commerce;

use App\Enums\LocationKind;
use App\Models\Branch;
use App\Models\Company;
use App\Models\StockLocation;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LocationService
{
    public function __construct(private readonly CompanyContext $context) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, array $attributes): StockLocation
    {
        return DB::transaction(function () use ($company, $attributes) {
            $this->context->ensureId((int) $company->id);

            return StockLocation::query()->create($this->fields($attributes) + [
                'company_id' => $company->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(StockLocation $location, array $attributes): StockLocation
    {
        return DB::transaction(function () use ($location, $attributes) {
            $this->context->ensureId((int) $location->company_id);
            $fields = $this->fields($attributes, $location);

            if ($fields['parent_id'] === $location->id) {
                throw ValidationException::withMessages([
                    'parent_uuid' => 'A location cannot sit inside itself.',
                ]);
            }

            $location->fill($fields);
            $location->save();

            return $location->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function fields(array $attributes, ?StockLocation $current = null): array
    {
        $branch = Branch::query()->where('uuid', $attributes['branch_uuid'])->first();

        if (! $branch) {
            throw ValidationException::withMessages([
                'branch_uuid' => 'Choose a branch.',
            ]);
        }

        $parentId = null;

        if (! empty($attributes['parent_uuid'])) {
            $parent = StockLocation::query()->where('uuid', $attributes['parent_uuid'])->first();

            if (! $parent || (int) $parent->branch_id !== (int) $branch->id || ($current && $parent->is($current))) {
                throw ValidationException::withMessages([
                    'parent_uuid' => 'Choose a location in the same branch.',
                ]);
            }

            $parentId = $parent->id;
        }

        return [
            'branch_id' => $branch->id,
            'parent_id' => $parentId,
            'kind' => LocationKind::from($attributes['kind']),
            'code' => Str::upper(trim((string) $attributes['code'])),
            'name' => $attributes['name'],
            'is_active' => filter_var($attributes['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
