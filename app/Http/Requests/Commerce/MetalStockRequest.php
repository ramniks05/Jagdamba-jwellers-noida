<?php

namespace App\Http\Requests\Commerce;

use App\Enums\MetalStockKind;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetalStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('inventory.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $shop = fn ($query) => $query->where('company_id', $this->user()->company_id)->whereNull('deleted_at');

        return [
            'kind' => ['required', Rule::in([MetalStockKind::Opening->value, MetalStockKind::Purchase->value])],
            'branch_uuid' => ['required', 'uuid', Rule::exists('branches', 'uuid')->where($shop)],
            'metal_uuid' => ['required', 'uuid', Rule::exists('metal_types', 'uuid')->where($shop)],
            'purity_uuid' => ['required', 'uuid', Rule::exists('purities', 'uuid')->where($shop)],
            'grams' => ['required', 'numeric', 'gt:0', Limits::WEIGHT],
            'supplier_uuid' => ['required_if:kind,purchase', 'nullable', 'uuid', Rule::exists('suppliers', 'uuid')->where(fn ($query) => $query->where('company_id', $this->user()->company_id))],
        ];
    }
}
