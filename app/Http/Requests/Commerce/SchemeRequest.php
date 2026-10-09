<?php

namespace App\Http\Requests\Commerce;

use App\Models\GoldScheme;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', GoldScheme::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'bonus_value' => $this->filled('bonus_value') ? $this->input('bonus_value') : '0',
            'monthly_amount' => $this->filled('monthly_amount') ? $this->input('monthly_amount') : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'code' => ['required', 'regex:/^[A-Z0-9]{2,20}$/', Rule::unique('gold_schemes', 'code')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:120'],
            'installment_mode' => ['required', Rule::in(['fixed', 'variable'])],
            'monthly_amount' => ['nullable', 'numeric', 'gt:0', Limits::MONEY],
            'duration_months' => ['required', 'integer', 'between:1,120'],
            'bonus_type' => ['required', Rule::in(array_keys(config('schemes.bonus_types')))],
            'bonus_value' => ['required', 'numeric', 'gte:0', Limits::MONEY, Rule::when($this->input('bonus_type') === 'percent', ['lte:100'])],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Another scheme already uses this code.',
            'bonus_value.lte' => 'A percent bonus cannot be more than 100%.',
        ];
    }
}
