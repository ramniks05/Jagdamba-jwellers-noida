<?php

namespace App\Http\Requests\Commerce;

use App\Models\OldGoldExchange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OldGoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', OldGoldExchange::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'stone_weight' => $this->filled('stone_weight') ? $this->input('stone_weight') : '0',
            'melting_loss_percent' => $this->filled('melting_loss_percent') ? $this->input('melting_loss_percent') : '0',
            'deduction_amount' => $this->filled('deduction_amount') ? $this->input('deduction_amount') : '0',
            'refund' => $this->filled('refund') ? $this->input('refund') : '0',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $shop = fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at');

        return [
            'customer_uuid' => ['required', 'uuid', Rule::exists('customers', 'uuid')->where(fn ($query) => $query->where('company_id', $companyId))],
            'metal_uuid' => ['required', 'uuid', Rule::exists('metal_types', 'uuid')->where($shop)],
            'purity_uuid' => ['required', 'uuid', Rule::exists('purities', 'uuid')->where($shop)],
            'gross_weight' => ['required', 'numeric', 'gt:0'],
            'stone_weight' => ['required', 'numeric', 'gte:0'],
            'melting_loss_percent' => ['required', 'numeric', 'gte:0', 'lte:100'],
            'rate_per_gram' => ['required', 'numeric', 'gt:0'],
            'deduction_amount' => ['required', 'numeric', 'gte:0'],
            'testing_result' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'refund' => ['required', 'numeric', 'gte:0'],
        ];
    }
}
