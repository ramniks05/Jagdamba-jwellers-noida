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

    protected function prepareForValidation(): void
    {
        if ($this->input('rate_per_gram') === '') {
            $this->merge(['rate_per_gram' => null]);
        }

        $bill = trim((string) $this->input('supplier_bill_number', ''));
        $this->merge(['supplier_bill_number' => $bill === '' ? null : $bill]);
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
            'supplier_bill_number' => ['required_if:kind,purchase', 'nullable', 'string', 'max:40'],
            'rate_per_gram' => ['required_if:kind,purchase', 'nullable', 'numeric', 'gt:0', Limits::MONEY],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_bill_number.required_if' => 'Enter the supplier bill number.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rate_per_gram' => 'rate',
            'supplier_bill_number' => 'supplier bill number',
        ];
    }
}
