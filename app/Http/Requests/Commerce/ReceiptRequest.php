<?php

namespace App\Http\Requests\Commerce;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Payment::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('reference') === '') {
            $this->merge(['reference' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:80'],
        ];
    }
}
