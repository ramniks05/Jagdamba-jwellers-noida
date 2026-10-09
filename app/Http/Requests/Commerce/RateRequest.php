<?php

namespace App\Http\Requests\Commerce;

use App\Models\MetalRate;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', MetalRate::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('branch_uuid') === '') {
            $this->merge(['branch_uuid' => null]);
        }

        if ($this->input('note') === '') {
            $this->merge(['note' => null]);
        }

        if ($this->input('effective_at') === '' || $this->input('effective_at') === null) {
            $this->merge(['effective_at' => now()->format('Y-m-d H:i:s')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $shop = fn ($query) => $query->where('company_id', $this->user()->company_id)->whereNull('deleted_at');

        return [
            'metal_uuid' => ['required', 'uuid', Rule::exists('metal_types', 'uuid')->where($shop)],
            'purity_uuid' => ['required', 'uuid', Rule::exists('purities', 'uuid')->where($shop)],
            'branch_uuid' => ['nullable', 'uuid', Rule::exists('branches', 'uuid')->where($shop)],
            'rate_per_gram' => ['required', 'numeric', 'gt:0', Limits::RATE],
            'effective_at' => ['required', 'date', 'before_or_equal:'.now()->addDay()->endOfDay()->toDateTimeString()],
            'source' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rate_per_gram.max' => 'That rate looks too high. Check for an extra zero.',
            'effective_at.before_or_equal' => 'A rate can be saved for today or tomorrow, not further ahead.',
        ];
    }
}
