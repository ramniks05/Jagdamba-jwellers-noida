<?php

namespace App\Http\Requests\Masters;

use App\Models\ChargeMethod;
use Illuminate\Foundation\Http\FormRequest;

class ChargeMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $method = $this->route('chargeMethod');

        if (is_string($method)) {
            $method = ChargeMethod::query()->where('uuid', $method)->first();
        }

        return $method instanceof ChargeMethod && (bool) $this->user()?->can('update', $method);
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('sort_order') === null || $this->input('sort_order') === '') {
            $this->merge(['sort_order' => 0]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'sort_order' => ['required', 'integer', 'between:0,9999'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
