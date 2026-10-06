<?php

namespace App\Http\Requests\Commerce;

use App\Models\RepairOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RepairRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', RepairOrder::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'estimated_cost' => $this->filled('estimated_cost') ? $this->input('estimated_cost') : '0',
            'item_uuid' => $this->filled('item_uuid') ? $this->input('item_uuid') : null,
            'expected_on' => $this->filled('expected_on') ? $this->input('expected_on') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'customer_uuid' => ['required', 'uuid', Rule::exists('customers', 'uuid')->where(fn ($query) => $query->where('company_id', $companyId))],
            'item_uuid' => ['nullable', 'uuid', Rule::exists('items', 'uuid')->where(fn ($query) => $query->where('company_id', $companyId))],
            'description' => ['required', 'string', 'max:160'],
            'problem' => ['required', 'string', 'max:500'],
            'gross_weight' => ['required', 'numeric', 'gt:0'],
            'technician' => ['nullable', 'string', 'max:80'],
            'estimated_cost' => ['required', 'numeric', 'gte:0'],
            'expected_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
