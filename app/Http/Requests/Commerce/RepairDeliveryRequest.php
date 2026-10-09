<?php

namespace App\Http\Requests\Commerce;

use App\Enums\PaymentMethod;
use App\Models\RepairOrder;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RepairDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $repair = $this->route('repair');

        return $repair instanceof RepairOrder && (bool) $this->user()?->can('update', $repair);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'final_charge' => $this->filled('final_charge') ? $this->input('final_charge') : '0',
            'payment' => $this->filled('payment') ? $this->input('payment') : '0',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'final_charge' => ['required', 'numeric', 'gte:0', Limits::MONEY],
            'payment' => ['required', 'numeric', 'gte:0', Limits::MONEY],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:80'],
        ];
    }
}
