<?php

namespace App\Http\Requests\Commerce;

use App\Models\GirviPledge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GirviRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', GirviPledge::class);
    }

    protected function prepareForValidation(): void
    {
        $mode = $this->input('loan_mode') === 'amount' ? 'amount' : 'percent';
        $pieces = collect($this->input('pieces', []))
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row) {
                $stone = $row['stone_weight'] ?? null;
                $row['stone_weight'] = $stone === null || $stone === '' ? '0' : $stone;

                return $row;
            })
            ->values()
            ->all();

        if ($pieces === [] && $this->filled('description')) {
            $pieces = [[
                'description' => $this->input('description'),
                'metal_uuid' => $this->input('metal_uuid'),
                'purity_uuid' => $this->input('purity_uuid'),
                'gross_weight' => $this->input('gross_weight'),
                'stone_weight' => $this->filled('stone_weight') ? $this->input('stone_weight') : '0',
                'rate_per_gram' => $this->input('rate_per_gram'),
            ]];
        }

        $this->merge([
            'loan_mode' => $mode,
            'pieces' => $pieces,
            'loan_percent' => $mode === 'percent' ? ($this->filled('loan_percent') ? $this->input('loan_percent') : '0') : null,
            'loan_amount' => $mode === 'amount' ? ($this->filled('loan_amount') ? $this->input('loan_amount') : null) : null,
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
            'pieces' => ['required', 'array', 'min:1', 'max:30'],
            'pieces.*.description' => ['required', 'string', 'max:160'],
            'pieces.*.metal_uuid' => ['required', 'uuid', Rule::exists('metal_types', 'uuid')->where($shop)],
            'pieces.*.purity_uuid' => ['required', 'uuid', Rule::exists('purities', 'uuid')->where($shop)],
            'pieces.*.gross_weight' => ['required', 'numeric', 'gt:0'],
            'pieces.*.stone_weight' => ['required', 'numeric', 'gte:0'],
            'pieces.*.rate_per_gram' => ['required', 'numeric', 'gt:0'],
            'loan_mode' => ['required', Rule::in(['percent', 'amount'])],
            'loan_percent' => ['required_if:loan_mode,percent', 'nullable', 'numeric', 'gt:0', 'lte:100'],
            'loan_amount' => ['required_if:loan_mode,amount', 'nullable', 'numeric', 'gt:0'],
            'interest_percent' => ['required', 'numeric', 'gt:0', 'lte:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
