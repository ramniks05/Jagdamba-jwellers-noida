<?php

namespace App\Services\Masters;

use App\Models\Category;
use App\Models\Company;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function __construct(private readonly MasterRecordService $records) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, array $attributes): Category
    {
        /** @var Category $category */
        $category = $this->records->create(Category::class, $company, $attributes, function (Category $category, array $attributes) {
            $this->assignParent($category, $attributes['parent_uuid'] ?? null);
        });

        return $category;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Category $category, array $attributes): Category
    {
        /** @var Category $category */
        $category = $this->records->update($category, $attributes, function (Category $category, array $attributes) {
            $this->assignParent($category, $attributes['parent_uuid'] ?? null);
        });

        return $category;
    }

    public function delete(Category $category): void
    {
        $this->records->delete($category);
    }

    private function assignParent(Category $category, ?string $uuid): void
    {
        if ($uuid === null || $uuid === '') {
            $category->parent_id = null;

            return;
        }

        $parent = Category::query()->where('uuid', $uuid)->first();

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_uuid' => 'Choose a category from this shop.',
            ]);
        }

        $this->assertParent($category, $parent);
        $category->parent_id = $parent->id;
    }

    private function assertParent(Category $category, Category $parent): void
    {
        if ($category->exists && $parent->is($category)) {
            throw ValidationException::withMessages([
                'parent_uuid' => 'A category cannot be its own parent.',
            ]);
        }

        $all = Category::query()->get(['id', 'parent_id']);
        $blocked = [];

        if ($category->exists) {
            $blocked[$category->id] = true;
            $queue = [$category->id];

            while ($queue !== []) {
                $current = array_shift($queue);

                foreach ($all as $item) {
                    if ((int) $item->parent_id === (int) $current && ! isset($blocked[$item->id])) {
                        $blocked[$item->id] = true;
                        $queue[] = $item->id;
                    }
                }
            }
        }

        if (isset($blocked[$parent->id])) {
            throw ValidationException::withMessages([
                'parent_uuid' => 'Choose a parent that is not under this category.',
            ]);
        }
    }
}
