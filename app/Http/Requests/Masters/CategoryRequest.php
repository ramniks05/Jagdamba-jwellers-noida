<?php

namespace App\Http\Requests\Masters;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

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

    public function existing(): ?Model
    {
        return $this->recordFromRoute('category', Category::class);
    }
}
