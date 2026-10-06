<?php

namespace App\Http\Requests\Commerce;

use App\Enums\LocationKind;
use App\Models\StockLocation;
use App\Support\IdentityRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $location = $this->route('location');

        if ($location instanceof StockLocation) {
            return (bool) $this->user()?->can('update', $location);
        }

        return (bool) $this->user()?->can('create', StockLocation::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper(trim((string) $this->input('code'))),
        ]);

        if ($this->input('parent_uuid') === '') {
            $this->merge(['parent_uuid' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $location = $this->route('location');
        $ignore = $location instanceof StockLocation ? $location->id : null;
        $shop = fn ($query) => $query->where('company_id', $this->user()->company_id)->whereNull('deleted_at');

        return [
            'branch_uuid' => ['required', 'uuid', Rule::exists('branches', 'uuid')->where($shop)],
            'parent_uuid' => ['nullable', 'uuid', Rule::exists('stock_locations', 'uuid')->where($shop)],
            'kind' => ['required', Rule::enum(LocationKind::class)],
            'code' => ['required', 'regex:'.IdentityRules::CODE, Rule::unique('stock_locations', 'code')->where(fn ($query) => $query->where('company_id', $this->user()->company_id))->ignore($ignore)],
            'name' => ['required', 'string', 'max:80'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
