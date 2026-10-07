<?php

namespace App\Http\Requests\Commerce;

use App\Support\IdentityRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pan' => Str::upper(trim((string) $this->input('pan'))),
            'gstin' => Str::upper(trim((string) $this->input('gstin'))),
            'country' => trim((string) $this->input('country')) ?: 'India',
        ]);

        foreach (['email', 'pan', 'gstin'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $newCustomer = in_array($this->input('intent'), ['new', 'update'], true);

        return [
            'intent' => ['required', Rule::in(['known', 'new', 'confirm', 'update'])],
            'mobile' => ['required', 'string', 'max:20', 'regex:/^(?:\+?91[\s-]?)?[6-9][0-9]{9}$/'],
            'name' => [Rule::requiredIf($newCustomer), 'nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'address_line1' => [Rule::requiredIf($newCustomer), 'nullable', 'string', 'max:200'],
            'city' => [Rule::requiredIf($newCustomer), 'nullable', 'string', 'max:100'],
            'state' => [Rule::requiredIf($newCustomer), 'nullable', 'string', 'max:100'],
            'postal_code' => [Rule::requiredIf($newCustomer), 'nullable', 'string', 'max:12'],
            'country' => ['nullable', 'string', 'max:100'],
            'pan' => ['nullable', 'regex:'.IdentityRules::PAN],
            'gstin' => ['nullable', 'regex:'.IdentityRules::GSTIN],
        ];
    }
}
