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
        $zero = fn ($value) => trim((string) $value) === '' ? '0' : $value;
        $blank = fn ($value) => trim((string) $value) === '' ? null : $value;
        $lines = collect($this->input('lines', []))
            ->filter(fn ($row) => is_array($row) && collect(['name', 'gross_weight', 'amount'])->contains(fn ($key) => trim((string) ($row[$key] ?? '')) !== ''))
            ->map(fn (array $row) => array_merge($row, [
                'item_code' => Str::upper(trim((string) ($row['item_code'] ?? ''))),
                'huid' => $blank(Str::upper(trim((string) ($row['huid'] ?? '')))),
                'category_uuid' => $blank($row['category_uuid'] ?? null),
                'stone_weight' => $zero($row['stone_weight'] ?? ''),
                'other_weight' => $zero($row['other_weight'] ?? ''),
                'stone_value' => $zero($row['stone_value'] ?? ''),
                'wastage_percent' => $zero($row['wastage_percent'] ?? ''),
                'labour_per_gram' => $zero($row['labour_per_gram'] ?? ''),
                'making_method_uuid' => $blank($row['making_method_uuid'] ?? null),
                'making_value' => $zero($row['making_value'] ?? ''),
                'wastage_method_uuid' => $blank($row['wastage_method_uuid'] ?? null),
                'wastage_value' => $zero($row['wastage_value'] ?? ''),
            ]))
            ->values()
            ->all();

        $this->merge([
            'lines' => $lines,
            'supplier_bill_number' => $blank(trim((string) $this->input('supplier_bill_number'))),
            'discount' => $zero($this->input('discount')),
            'gst_percent' => $zero($this->input('gst_percent')),
            'pricing' => $this->input('pricing') === 'amount' ? 'amount' : 'rate',
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
        $code = ['required', 'regex:/^[A-Z0-9][A-Z0-9._\/-]{0,39}$/', 'distinct',
            Rule::unique('items', 'item_code')->where(fn ($query) => $query->where('company_id', $companyId)),
            Rule::unique('items', 'sku')->where(fn ($query) => $query->where('company_id', $companyId)),
        ];

        return [
            'supplier_uuid' => ['required', 'uuid', Rule::exists('suppliers', 'uuid')->where(fn ($query) => $query->where('company_id', $companyId))],
            'supplier_bill_number' => ['nullable', 'string', 'max:80'],
            'purchased_on' => ['required', 'date', 'before_or_equal:today'],
            'location_uuid' => ['required', 'uuid', Rule::exists('stock_locations', 'uuid')->where($shop)],
            'pricing' => ['required', 'in:rate,amount'],
            'discount' => ['required', 'numeric', 'gte:0'],
            'gst_percent' => ['required', 'numeric', 'gte:0', 'lte:28'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.name' => ['required', 'string', 'max:160'],
            'lines.*.item_code' => $code,
            'lines.*.category_uuid' => ['nullable', 'uuid', Rule::exists('categories', 'uuid')->where($shop)],
            'lines.*.metal_uuid' => ['required', 'uuid', Rule::exists('metal_types', 'uuid')->where($shop)],
            'lines.*.purity_uuid' => ['required', 'uuid', Rule::exists('purities', 'uuid')->where($shop)],
            'lines.*.gross_weight' => ['required', 'numeric', 'gt:0'],
            'lines.*.stone_weight' => ['required', 'numeric', 'gte:0'],
            'lines.*.other_weight' => ['required', 'numeric', 'gte:0'],
            'lines.*.stone_value' => ['required', 'numeric', 'gte:0'],
            'lines.*.rate_per_gram' => ['exclude_if:pricing,amount', 'required', 'numeric', 'gt:0'],
            'lines.*.wastage_percent' => ['required', 'numeric', 'gte:0', 'lte:100'],
            'lines.*.labour_per_gram' => ['required', 'numeric', 'gte:0'],
            'lines.*.amount' => ['exclude_if:pricing,rate', 'required', 'numeric', 'gt:0'],
            'lines.*.making_method_uuid' => ['nullable', 'uuid', Rule::exists('charge_methods', 'uuid')->where(fn ($query) => $shop($query)->where('applies_to', ChargeAppliesTo::Making->value))],
            'lines.*.making_value' => ['required', 'numeric', 'gte:0'],
            'lines.*.wastage_method_uuid' => ['nullable', 'uuid', Rule::exists('charge_methods', 'uuid')->where(fn ($query) => $shop($query)->where('applies_to', ChargeAppliesTo::Wastage->value))],
            'lines.*.wastage_value' => ['required', 'numeric', 'gte:0'],
            'lines.*.huid' => ['nullable', 'string', 'max:32'],
            'payments' => ['array'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $names = [
            'name' => 'name', 'item_code' => 'item code', 'category_uuid' => 'category', 'metal_uuid' => 'metal',
            'purity_uuid' => 'purity', 'gross_weight' => 'gross weight', 'stone_weight' => 'stone weight',
            'other_weight' => 'other weight', 'stone_value' => 'stone charge', 'rate_per_gram' => 'rate per gram',
            'wastage_percent' => 'wastage %', 'labour_per_gram' => 'labour per gram', 'amount' => 'amount',
            'making_method_uuid' => 'selling making', 'making_value' => 'selling making', 'wastage_method_uuid' => 'selling wastage',
            'wastage_value' => 'selling wastage', 'huid' => 'HUID',
        ];
        $attributes = [
            'supplier_uuid' => 'supplier',
            'supplier_bill_number' => 'supplier bill number',
            'purchased_on' => 'bill date',
            'location_uuid' => 'kept at',
            'gst_percent' => 'GST %',
        ];

        foreach (array_keys((array) $this->input('lines', [])) as $index) {
            foreach ($names as $key => $label) {
                $attributes['lines.'.$index.'.'.$key] = 'piece '.((int) $index + 1).' '.$label;
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one piece.',
            'lines.*.item_code.unique' => 'The :attribute is already used by another piece.',
            'lines.*.item_code.distinct' => 'The :attribute is the same as another piece on this bill.',
            'purchased_on.before_or_equal' => 'The bill date cannot be in the future.',
        ];
    }
}
