<?php

namespace App\Http\Requests\Commerce;

use App\Models\AdvanceOrder;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvanceOrderUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof AdvanceOrder && (bool) $this->user()?->can('update', $order);
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
        return [
            'description' => ['required', 'string', 'max:160'],
            'design_notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:8'],
            'lines.*.metal_uuid' => ['required', 'uuid', Rule::exists('metal_types', 'uuid')->where(fn ($query) => $query->where('company_id', $this->user()->company_id)->whereNull('deleted_at'))],
            'lines.*.purity_uuid' => ['required', 'uuid', Rule::exists('purities', 'uuid')->where(fn ($query) => $query->where('company_id', $this->user()->company_id)->whereNull('deleted_at'))],
            'lines.*.expected_weight' => ['required', 'numeric', 'gt:0', Limits::WEIGHT],
            'lines.*.rate_per_gram' => ['required', 'numeric', 'gt:0', Limits::RATE],
            'lines.*.estimated_making' => ['nullable', 'numeric', 'gte:0', Limits::MONEY],
            'due_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
