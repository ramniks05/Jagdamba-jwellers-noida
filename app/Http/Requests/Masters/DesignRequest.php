<?php

namespace App\Http\Requests\Masters;

use App\Models\Design;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DesignRequest extends CatalogRequest
{
    public function authorize(): bool
    {
        $design = $this->existing();

        if ($design) {
            return (bool) $this->user()?->can('update', $design);
        }

        return (bool) $this->user()?->can('create', Design::class);
    }

    public function modelClass(): string
    {
        return Design::class;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'design_number' => Str::upper(trim((string) $this->input('design_number'))),
            'description' => $this->input('description') === '' ? null : $this->input('description'),
            'collection_uuid' => $this->input('collection_uuid') === '' ? null : $this->input('collection_uuid'),
            'category_uuid' => $this->input('category_uuid') === '' ? null : $this->input('category_uuid'),
        ]);

        if ($this->input('sort_order') === null || $this->input('sort_order') === '') {
            $this->merge(['sort_order' => 0]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $design = $this->existing();

        return [
            'design_number' => [
                'required',
                'regex:/^[A-Z0-9][A-Z0-9._\/-]{0,39}$/',
                Rule::unique('designs', 'design_number')
                    ->where(fn ($query) => $query->where('company_id', $this->user()->company_id))
                    ->ignore($design?->id),
            ],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'collection_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('collections', 'uuid')->where(
                    fn ($query) => $query->where('company_id', $this->user()->company_id)->whereNull('deleted_at'),
                ),
            ],
            'category_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('categories', 'uuid')->where(
                    fn ($query) => $query->where('company_id', $this->user()->company_id)->whereNull('deleted_at'),
                ),
            ],
            'sort_order' => ['required', 'integer', 'between:0,9999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function existing(): ?Design
    {
        return $this->recordFromRoute('design', Design::class);
    }
}
