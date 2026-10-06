<?php

namespace App\Http\Requests\Foundation;

use App\Support\IdentityRules;

trait NormalizesShopIdentity
{
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['code', 'gstin', 'pan', 'currency_code', 'email'] as $field) {
            if (! $this->exists($field) || ! is_string($this->input($field))) {
                continue;
            }

            $value = trim($this->input($field));

            if (in_array($field, ['code', 'gstin', 'pan', 'currency_code'], true)) {
                $value = strtoupper($value);
            }

            if ($field === 'email') {
                $value = strtolower($value);
            }

            $merge[$field] = $value;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, string>
     */
    public function identityMessages(): array
    {
        return [
            'gstin.regex' => 'Enter a valid 15-character GSTIN.',
            'pan.regex' => 'Enter a valid 10-character PAN.',
            'code.regex' => 'Use 2 to 20 letters or digits.',
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function contactRules(): array
    {
        return [
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'gstin' => ['nullable', 'regex:'.IdentityRules::GSTIN],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function addressRules(): array
    {
        return [
            'address_line1' => ['required', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:12'],
            'country' => ['required', 'string', 'max:100'],
        ];
    }
}
