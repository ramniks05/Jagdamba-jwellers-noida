<?php

namespace App\Http\Requests\Foundation;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', Setting::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', Rule::in(array_keys(config('foundation.settings')))],
            'settings.*.value' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $rows = $this->input('settings', []);
            $catalog = config('foundation.settings');
            $submitted = [];

            foreach ($rows as $index => $row) {
                $key = $row['key'] ?? null;

                if (! is_string($key) || ! isset($catalog[$key])) {
                    continue;
                }

                if (in_array($key, $submitted, true)) {
                    $validator->errors()->add('settings', 'Duplicate settings were submitted.');

                    continue;
                }

                $submitted[] = $key;
                $value = $row['value'] ?? null;

                if (($catalog[$key]['type'] ?? '') === 'boolean') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }

                $check = validator(
                    ['value' => $value],
                    ['value' => $catalog[$key]['rules']],
                    [],
                    ['value' => $catalog[$key]['label']],
                );

                if ($check->fails()) {
                    foreach ($check->errors()->all() as $message) {
                        $validator->errors()->add("settings.$index.value", $message);
                    }
                }
            }

            $missing = array_diff(array_keys($catalog), $submitted);

            if ($missing !== []) {
                $validator->errors()->add('settings', 'Submit every setting.');
            }

            $parsed = [];

            foreach ($rows as $row) {
                if (isset($row['key'])) {
                    $parsed[$row['key']] = $row['value'] ?? null;
                }
            }

            foreach ([
                ['currency.thousand_separator', 'currency.decimal_separator'],
                ['number.thousand_separator', 'number.decimal_separator'],
            ] as [$thousandKey, $decimalKey]) {
                $thousand = (string) ($parsed[$thousandKey] ?? '');
                $decimal = (string) ($parsed[$decimalKey] ?? '');

                if ($thousand !== '' && $thousand === $decimal) {
                    $validator->errors()->add('settings', 'Thousand and decimal separators must be different.');
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $values = [];

        foreach ($this->validated('settings') as $row) {
            $values[$row['key']] = $row['value'] ?? null;
        }

        return $values;
    }
}
