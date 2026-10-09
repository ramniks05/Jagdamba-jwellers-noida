<?php

namespace App\Http\Requests\Commerce;

use App\Enums\PaymentMethod;
use App\Models\GirviPledge;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GirviSettleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pledge = $this->route('pledge');

        return $pledge instanceof GirviPledge && (bool) $this->user()?->can('update', $pledge);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'action' => $this->input('action') === 'interest' ? 'interest' : 'release',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['release', 'interest'])],
            'months' => ['required', 'integer', 'min:0', 'max:120'],
            'payment' => ['required', 'numeric', 'gt:0', Limits::MONEY],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:80'],
        ];
    }
}
