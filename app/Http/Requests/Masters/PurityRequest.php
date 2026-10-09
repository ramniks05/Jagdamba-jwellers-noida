<?php

namespace App\Http\Requests\Masters;

use App\Models\MetalType;
use App\Models\Purity;
use App\Support\IdentityRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PurityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purity = $this->existing();

        if ($purity) {
            return (bool) $this->user()?->can('update', $purity);
        }

        return (bool) $this->user()?->can('create', Purity::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper(trim((string) $this->input('code'))),
        ]);

        if ($this->input('sort_order') === null || $this->input('sort_order') === '') {
            $this->merge(['sort_order' => 0]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $metal = $this->metal();
        $purity = $this->existing();

        return [
            'name' => ['required', 'string', 'max:80'],
            'code' => [
                'required',
                'regex:'.IdentityRules::CODE,
                Rule::unique('purities', 'code')
                    ->where(fn ($query) => $query
                        ->where('company_id', $this->user()->company_id)
                        ->where('metal_type_id', $metal?->id))
                    ->ignore($purity?->id),
            ],
            'fineness_percent' => ['required', 'numeric', 'gt:0', 'lte:100', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'sort_order' => ['required', 'integer', 'between:0,9999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use 2 to 20 letters or numbers for the code, with no spaces.',
            'fineness_percent.regex' => 'Enter the fineness as a percent, such as 91.6.',
        ];
    }

    public function metal(): ?MetalType
    {
        $value = $this->route('metal');

        if ($value instanceof MetalType) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return MetalType::query()->where('uuid', $value)->first();
    }

    public function existing(): ?Purity
    {
        $metal = $this->metal();
        $value = $this->route('purity');

        if (! $metal) {
            return null;
        }

        if ($value instanceof Purity) {
            return (int) $value->metal_type_id === (int) $metal->id ? $value : null;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Purity::query()->where('metal_type_id', $metal->id)->where('uuid', $value)->first();
    }
}
