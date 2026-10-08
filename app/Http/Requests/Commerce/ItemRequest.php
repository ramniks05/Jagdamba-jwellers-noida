<?php

namespace App\Http\Requests\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Models\Item;
use App\Support\StoneRate;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        if ($item instanceof Item) {
            return (bool) $this->user()?->can('update', $item);
        }

        return (bool) $this->user()?->can('create', Item::class);
    }

    protected function prepareForValidation(): void
    {
        $itemCode = Str::upper(trim((string) $this->input('item_code')));
        $sku = Str::upper(trim((string) $this->input('sku')));

        $stones = $this->exists('stones') ? $this->stoneRows($this->input('stones')) : null;
        $stoneWeight = $this->input('stone_weight') === '' || $this->input('stone_weight') === null ? '0' : $this->input('stone_weight');
        $stoneValue = $this->input('stone_value') === '' || $this->input('stone_value') === null ? '0' : $this->input('stone_value');

        if (is_array($stones)) {
            $weight = BigDecimal::zero();
            $value = BigDecimal::zero();

            foreach ($stones as $stone) {
                $weight = $weight->plus($stone['weight']);
                $value = $value->plus($stone['value']);
            }

            $stoneWeight = (string) $weight->toScale(3, RoundingMode::HalfUp);
            $stoneValue = (string) $value->toScale(2, RoundingMode::HalfUp);
        }

        $payload = [
            'item_code' => $itemCode,
            'sku' => $sku !== '' ? $sku : $itemCode,
            'barcode' => trim((string) $this->input('barcode')) ?: null,
            'huid' => Str::upper(trim((string) $this->input('huid'))),
            'stone_weight' => $stoneWeight,
            'other_weight' => $this->input('other_weight') === '' || $this->input('other_weight') === null ? '0' : $this->input('other_weight'),
            'making_value' => $this->input('making_value') === '' || $this->input('making_value') === null ? '0' : $this->input('making_value'),
            'wastage_value' => $this->input('wastage_value') === '' || $this->input('wastage_value') === null ? '0' : $this->input('wastage_value'),
            'stone_value' => $stoneValue,
            'cost_price' => $this->input('cost_price') === '' || $this->input('cost_price') === null ? '0' : $this->input('cost_price'),
            'selling_price' => $this->input('selling_price') === '' || $this->input('selling_price') === null ? '0' : $this->input('selling_price'),
            'mrp' => $this->input('mrp') === '' || $this->input('mrp') === null ? '0' : $this->input('mrp'),
        ];

        if (is_array($stones)) {
            $payload['stones'] = $stones;
        }

        $this->merge($payload);

        foreach (['category_uuid', 'brand_uuid', 'collection_uuid', 'design_uuid', 'making_method_uuid', 'wastage_method_uuid', 'rfid', 'certificate_number', 'hallmark', 'huid', 'notes'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $item = $this->route('item');
        $ignore = $item instanceof Item ? $item->id : null;
        $shop = fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at');

        return [
            'name' => ['required', 'string', 'max:160'],
            'item_code' => ['required', 'regex:/^[A-Z0-9][A-Z0-9._\/-]{0,39}$/', Rule::unique('items', 'item_code')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($ignore)],
            'sku' => ['required', 'regex:/^[A-Z0-9][A-Z0-9._\/-]{0,39}$/', Rule::unique('items', 'sku')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($ignore)],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('items', 'barcode')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($ignore)],
            'rfid' => ['nullable', 'string', 'max:64'],
            'category_uuid' => ['nullable', 'uuid', Rule::exists('categories', 'uuid')->where($shop)],
            'brand_uuid' => ['nullable', 'uuid', Rule::exists('brands', 'uuid')->where($shop)],
            'collection_uuid' => ['nullable', 'uuid', Rule::exists('collections', 'uuid')->where($shop)],
            'design_uuid' => ['nullable', 'uuid', Rule::exists('designs', 'uuid')->where($shop)],
            'metal_uuid' => ['required', 'uuid', Rule::exists('metal_types', 'uuid')->where($shop)],
            'purity_uuid' => ['required', 'uuid', Rule::exists('purities', 'uuid')->where($shop)],
            'location_uuid' => ['required', 'uuid', Rule::exists('stock_locations', 'uuid')->where($shop)],
            'gross_weight' => ['required', 'numeric', 'gt:0'],
            'stones' => ['nullable', 'array'],
            'stones.*.name' => ['required', 'string', 'max:80'],
            'stones.*.weight' => ['required', 'numeric', 'gte:0'],
            'stones.*.value' => ['required', 'numeric', 'gte:0'],
            'stones.*.rate' => ['nullable', 'numeric', 'gte:0'],
            'stones.*.rate_unit' => ['nullable', Rule::in(StoneRate::UNITS)],
            'stone_weight' => ['required', 'numeric', 'gte:0'],
            'other_weight' => ['required', 'numeric', 'gte:0'],
            'making_method_uuid' => ['nullable', 'uuid', Rule::exists('charge_methods', 'uuid')->where(fn ($query) => $shop($query)->where('applies_to', ChargeAppliesTo::Making->value))],
            'making_value' => ['required', 'numeric', 'gte:0'],
            'wastage_method_uuid' => ['nullable', 'uuid', Rule::exists('charge_methods', 'uuid')->where(fn ($query) => $shop($query)->where('applies_to', ChargeAppliesTo::Wastage->value))],
            'wastage_value' => ['required', 'numeric', 'gte:0'],
            'stone_value' => ['required', 'numeric', 'gte:0'],
            'cost_price' => ['required', 'numeric', 'gte:0'],
            'selling_price' => ['required', 'numeric', 'gte:0'],
            'mrp' => ['required', 'numeric', 'gte:0'],
            'certificate_number' => ['nullable', 'string', 'max:40'],
            'hallmark' => ['nullable', 'string', 'max:40'],
            'huid' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'max:2048'],
            'old_gold_uuid' => [Rule::prohibitedIf($item instanceof Item), 'nullable', 'uuid', Rule::exists('old_gold_exchanges', 'uuid')->where(fn ($query) => $query->where('company_id', $companyId))],
        ];
    }

    /**
     * @return list<array{name: string, weight: string, value: string, rate: ?string, rate_unit: ?string}>
     */
    private function stoneRows(mixed $stones): array
    {
        $rows = [];

        foreach ((array) $stones as $stone) {
            if (! is_array($stone)) {
                continue;
            }

            $name = trim((string) ($stone['name'] ?? ''));
            $weight = trim((string) ($stone['weight'] ?? ''));
            $value = trim((string) ($stone['value'] ?? ''));
            $rate = trim((string) ($stone['rate'] ?? ''));

            if ($name === '' && ($weight === '' || $weight === '0') && ($value === '' || $value === '0') && ($rate === '' || $rate === '0')) {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'weight' => $weight === '' ? '0' : $weight,
                'value' => $value === '' ? '0' : $value,
                'rate' => $rate === '' ? null : $rate,
                'rate_unit' => trim((string) ($stone['rate_unit'] ?? '')) ?: null,
            ];
        }

        return $rows;
    }
}
