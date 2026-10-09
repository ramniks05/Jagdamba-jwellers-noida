<?php

namespace App\Http\Requests\Commerce;

use App\Models\MetalRate;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', MetalRate::class);
    }

    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('lines', []))
            ->filter(fn (mixed $line) => is_array($line) && ! empty($line['use']))
            ->map(function (array $line): array {
                unset($line['use']);

                return $line;
            })
            ->values()
            ->all();

        $this->merge([
            'lines' => $lines === [] ? null : $lines,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $shop = fn ($query) => $query->where('company_id', $this->user()->company_id)->whereNull('deleted_at');

        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purity_uuid' => ['required', 'uuid', Rule::exists('purities', 'uuid')->where($shop)],
            'lines.*.rate_per_gram' => ['required', 'numeric', 'gt:0', Limits::RATE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Choose at least one rate to save.',
            'lines.min' => 'Choose at least one rate to save.',
            'lines.*.rate_per_gram.max' => 'That rate looks too high. Check for an extra zero.',
        ];
    }
}
