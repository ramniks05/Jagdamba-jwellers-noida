<?php

namespace App\Http\Requests\Commerce;

use App\Enums\PaymentMethod;
use App\Models\AdvanceOrder;
use App\Support\Limits;
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
        $lines = $this->input('lines');

        if (! is_array($lines) || $lines === []) {
            if ($this->filled('metal_uuid')) {
                $lines = [[
                    'metal_uuid' => $this->input('metal_uuid'),
                    'purity_uuid' => $this->input('purity_uuid'),
                    'expected_weight' => $this->input('expected_weight'),
                    'rate_per_gram' => $this->input('rate_per_gram'),
                    'estimated_making' => $this->input('estimated_making'),
                ]];
            }
        }

        if (is_array($lines)) {
            $headerMaking = trim((string) $this->input('estimated_making')) === '' ? '0' : $this->input('estimated_making');
            $lines = array_values(array_map(function (array $row) {
                $row['estimated_making'] = trim((string) ($row['estimated_making'] ?? '')) === '' ? '0' : $row['estimated_making'];

                return $row;
            }, $lines));

            if ($headerMaking !== '0' && array_sum(array_map(fn (array $row) => (float) $row['estimated_making'], $lines)) === 0.0) {
                $lines[0]['estimated_making'] = $headerMaking;
            }
        }

        $this->merge([
            'lines' => $lines,
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
            'lines' => ['required', 'array', 'min:1', 'max:8'],
            'lines.*.metal_uuid' => ['required', 'uuid', Rule::exists('metal_types', 'uuid')->where($shop)],
            'lines.*.purity_uuid' => ['required', 'uuid', Rule::exists('purities', 'uuid')->where($shop)],
            'lines.*.expected_weight' => ['required', 'numeric', 'gt:0', Limits::WEIGHT],
            'lines.*.rate_per_gram' => ['nullable', 'numeric', 'gt:0', Limits::RATE],
            'lines.*.estimated_making' => ['nullable', 'numeric', 'gte:0', Limits::MONEY],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
            'advance' => ['required', 'numeric', 'gt:0', Limits::MONEY],
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
