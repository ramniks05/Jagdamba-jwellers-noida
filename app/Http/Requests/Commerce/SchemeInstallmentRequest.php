<?php

namespace App\Http\Requests\Commerce;

use App\Enums\PaymentMethod;
use App\Models\GoldScheme;
use App\Models\SchemeEnrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchemeInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');

        return $enrollment instanceof SchemeEnrollment && (bool) $this->user()?->can('create', GoldScheme::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:80'],
        ];
    }
}
