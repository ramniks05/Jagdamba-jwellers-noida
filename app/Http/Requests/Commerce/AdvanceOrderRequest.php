<?php

namespace App\Http\Requests\Commerce;

use App\Enums\PaymentMethod;
use App\Models\AdvanceOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvanceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', AdvanceOrder::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'estimated_making' => trim((string) $this->input('estimated_making')) === '' ? '0' : $this->input('estimated_making'),
            'due_on' => trim((string) $this->input('due_on')) === '' ? null : $this->input('due_on'),
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
            'customer_uuid' => ['required', 'uuid', Rule::exists('customers', 'uuid')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_system', false))],
            'description' => ['required', 'string', 'max:160'],
            'design_notes' => ['nullable', 'string', 'max:1000'],
            'metal_uuid' => ['required', 'uuid', Rule::exists('metal_types', 'uuid')->where($shop)],
            'purity_uuid' => ['required', 'uuid', Rule::exists('purities', 'uuid')->where($shop)],
            'expected_weight' => ['required', 'numeric', 'gt:0'],
            'estimated_making' => ['required', 'numeric', 'gte:0'],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
            'advance' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_uuid.required' => 'Choose the customer.',
            'customer_uuid.exists' => 'Choose the customer. An order cannot be in the walk-in name.',
            'due_on.after_or_equal' => 'The delivery date cannot be in the past.',
            'advance.gt' => 'Take an advance to book the order.',
        ];
    }
}
