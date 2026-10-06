<?php

namespace App\Http\Requests\Commerce;

use App\Enums\PaymentMethod;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Sale::class);
    }

    protected function prepareForValidation(): void
    {
        $payments = collect($this->input('payments', []))
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['amount'] ?? '')) !== '')
            ->values()
            ->all();

        $this->merge([
            'payments' => $payments,
            'discount' => trim((string) $this->input('discount')) === '' ? '0' : $this->input('discount'),
            'notes' => trim((string) $this->input('notes')) === '' ? null : $this->input('notes'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $shop = fn ($query) => $query->where('company_id', $this->user()->company_id)->whereNull('deleted_at');

        return [
            'customer_uuid' => ['required', 'uuid', Rule::exists('customers', 'uuid')->where($shop)],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['uuid', 'distinct'],
            'discount' => ['required', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payments' => ['array'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:80'],
        ];
    }
}
