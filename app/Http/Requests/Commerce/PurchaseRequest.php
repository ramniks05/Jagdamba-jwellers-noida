<?php

namespace App\Http\Requests\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\PaymentMethod;
use App\Models\Purchase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Purchase::class);
    }

    protected function prepareForValidation(): void
    {
        $itemCode = Str::upper(trim((string) $this->input('item_code')));

        $this->merge([
            'item_code' => $itemCode,
            'sku' => $itemCode,
            'stone_weight' => $this->filled('stone_weight') ? $this->input('stone_weight') : '0',
            'other_weight' => $this->filled('other_weight') ? $this->input('other_weight') : '0',
            'making_value' => $this->filled('making_value') ? $this->input('making_value') : '0',
            'wastage_value' => $this->filled('wastage_value') ? $this->input('wastage_value') : '0',
            'stone_value' => $this->filled('stone_value') ? $this->input('stone_value') : '0',
            'discount' => $this->filled('discount') ? $this->input('discount') : '0',
            'payments' => collect($this->input('payments', []))->filter(fn ($row) => is_array($row) && trim((string) ($row['amount'] ?? '')) !== '')->values()->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $shop = fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at');

        return [
            'supplier_uuid' => ['required', 'uuid', Rule::exists('suppliers', 'uuid')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:160'],
            'item_code' => ['required', 'regex:/^[A-Z0-9][A-Z0-9._\/-]{0,39}$/', Rule::unique('items', 'item_code')->where(fn ($query) => $query->where('company_id', $companyId))],
            'sku' => ['required', 'regex:/^[A-Z0-9][A-Z0-9._\/-]{0,39}$/'],
            'metal_uuid' => ['required', 'uuid', Rule::exists('metal_types', 'uuid')->where($shop)],
            'purity_uuid' => ['required', 'uuid', Rule::exists('purities', 'uuid')->where($shop)],
            'location_uuid' => ['required', 'uuid', Rule::exists('stock_locations', 'uuid')->where($shop)],
            'gross_weight' => ['required', 'numeric', 'gt:0'],
            'stone_weight' => ['required', 'numeric', 'gte:0'],
            'other_weight' => ['required', 'numeric', 'gte:0'],
            'making_method_uuid' => ['nullable', 'uuid', Rule::exists('charge_methods', 'uuid')->where(fn ($query) => $shop($query)->where('applies_to', ChargeAppliesTo::Making->value))],
            'making_value' => ['required', 'numeric', 'gte:0'],
            'wastage_method_uuid' => ['nullable', 'uuid', Rule::exists('charge_methods', 'uuid')->where(fn ($query) => $shop($query)->where('applies_to', ChargeAppliesTo::Wastage->value))],
            'wastage_value' => ['required', 'numeric', 'gte:0'],
            'stone_value' => ['required', 'numeric', 'gte:0'],
            'discount' => ['required', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payments' => ['array'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:80'],
        ];
    }
}
