<?php

namespace App\Services\Masters;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Company;
use App\Models\Design;
use Illuminate\Validation\ValidationException;

class DesignService
{
    public function __construct(private readonly MasterRecordService $records) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, array $attributes): Design
    {
        /** @var Design $design */
        $design = $this->records->create(Design::class, $company, $attributes, function (Design $design, array $attributes) {
            $this->assignLinks($design, $attributes);
        });

        return $design;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Design $design, array $attributes): Design
    {
        /** @var Design $design */
        $design = $this->records->update($design, $attributes, function (Design $design, array $attributes) {
            $this->assignLinks($design, $attributes);
        });

        return $design;
    }

    public function delete(Design $design): void
    {
        $this->records->delete($design);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assignLinks(Design $design, array $attributes): void
    {
        $design->collection_id = $this->idFor(Collection::class, $attributes['collection_uuid'] ?? null, 'collection_uuid');
        $design->category_id = $this->idFor(Category::class, $attributes['category_uuid'] ?? null, 'category_uuid');
    }

    /**
     * @param  class-string<Category|Collection>  $class
     */
    private function idFor(string $class, ?string $uuid, string $field): ?int
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        $record = $class::query()->where('uuid', $uuid)->first();

        if (! $record) {
            throw ValidationException::withMessages([
                $field => 'Choose a record from this shop.',
            ]);
        }

        return (int) $record->id;
    }
}
