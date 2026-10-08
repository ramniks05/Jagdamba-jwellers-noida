<?php

namespace App\Http\Requests\Commerce;

use App\Enums\PaymentMethod;
use App\Models\AdvanceOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvanceOrderActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof AdvanceOrder && (bool) $this->user()?->can('update', $order);
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('orders.cancel') && trim((string) $this->input('refund')) === '') {
            $this->merge(['refund' => '0']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->routeIs('orders.cancel')) {
            return [
                'refund' => ['required', 'numeric', 'gte:0'],
                'method' => ['required', Rule::enum(PaymentMethod::class)],
                'reference' => ['nullable', 'string', 'max:80'],
            ];
        }

        if ($this->routeIs('orders.advance')) {
            return [
                'amount' => ['required', 'numeric', 'gt:0'],
                'method' => ['required', Rule::enum(PaymentMethod::class)],
                'reference' => ['nullable', 'string', 'max:80'],
            ];
        }

        return [];
    }
}
