<?php

namespace App\Http\Requests\Masters;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class CategoryRequest extends CatalogRequest
{
    public function modelClass(): string
    {
        return Category::class;
    }

    public function routeKey(): string
    {
        return 'category';
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->input('parent_uuid') === '') {
            $this->merge(['parent_uuid' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'parent_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('categories', 'uuid')->where(
                    fn ($query) => $query->where('company_id', $this->user()->company_id)->whereNull('deleted_at'),
                ),
            ],
        ]);
    }

    /**
     * "Rings" may sit under both Gold and Silver, but not twice under one parent.
     */
    protected function uniqueName(): Unique
    {
        $parentId = $this->input('parent_uuid')
            ? Category::query()->where('uuid', $this->input('parent_uuid'))->value('id')
            : null;

        return parent::uniqueName()->where(fn ($query) => $parentId
            ? $query->where('parent_id', $parentId)
            : $query->whereNull('parent_id'));
    }

    public function existing(): ?Model
    {
        return $this->recordFromRoute('category', Category::class);
    }
}
