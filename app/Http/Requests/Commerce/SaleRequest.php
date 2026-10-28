<?php

namespace App\Http\Requests\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\PaymentMethod;
use App\Enums\RetailTaxMode;
use App\Models\Sale;
use App\Support\Limits;
use App\Support\StoneRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sale = $this->route('sale');

        if ($sale instanceof Sale) {
            return (bool) $this->user()?->can('update', $sale);
        }

        return (bool) $this->user()?->can('create', Sale::class);
    }

    protected function prepareForValidation(): void
    {
        $payments = collect($this->input('payments', []))
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['amount'] ?? '')) !== '')
            ->values()
            ->all();

        $piece = $this->input('new_piece');

        if (! is_array($piece) || trim((string) ($piece['name'] ?? '')) === '') {
            $piece = null;
        } else {
            foreach (['stone_weight', 'other_weight', 'making_value', 'wastage_value', 'stone_value'] as $field) {
                if (! isset($piece[$field]) || $piece[$field] === '') {
                    $piece[$field] = '0';
                }
            }

            foreach (['making_method_uuid', 'wastage_method_uuid'] as $field) {
                if (($piece[$field] ?? '') === '') {
                    $piece[$field] = null;
                }
            }

            $piece['stones'] = $this->stoneRows($piece['stones'] ?? []);
            $piece['huid'] = $this->huid($piece['huid'] ?? null);
            $piece['own'] = filter_var($piece['own'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        $pieces = [];

        foreach ((array) $this->input('new_pieces', []) as $row) {
            if (! is_array($row) || trim((string) ($row['name'] ?? '')) === '') {
                continue;
            }

            $pieces[] = $this->pieceRow($row);
        }

        $soldAt = trim((string) $this->input('sold_at', ''));

        if ($soldAt === '') {
            $soldAt = now(config('app.timezone'))->format('Y-m-d\TH:i');
        }

        $retailItems = [];

        foreach ((array) $this->input('retail_items', []) as $row) {
            if (! is_array($row) || trim((string) ($row['name'] ?? '')) === '') {
                continue;
            }

            $retailItems[] = [
                'product_uuid' => trim((string) ($row['product_uuid'] ?? '')) ?: null,
                'name' => trim((string) $row['name']),
                'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                'unit_price' => trim((string) ($row['unit_price'] ?? '')),
                'tax_mode' => (string) ($row['tax_mode'] ?? 'none'),
                'tax_percent' => trim((string) ($row['tax_percent'] ?? '')) === '' ? null : trim((string) $row['tax_percent']),
                'tax_amount' => trim((string) ($row['tax_amount'] ?? '')) === '' ? null : trim((string) $row['tax_amount']),
                'hsn_code' => trim((string) ($row['hsn_code'] ?? '')) === '' ? null : trim((string) $row['hsn_code']),
            ];
        }

        $this->merge([
            'payments' => $payments,
            'item_ids' => array_values(array_filter((array) $this->input('item_ids', []))),
            'new_piece' => $piece,
            'new_pieces' => $pieces,
            'retail_items' => $retailItems,
            'discount' => trim((string) $this->input('discount')) === '' ? '0' : $this->input('discount'),
            'notes' => trim((string) $this->input('notes')) === '' ? null : $this->input('notes'),
            'sold_at' => $soldAt,
            'tray_uuid' => trim((string) $this->input('tray_uuid', '')) === '' ? null : $this->input('tray_uuid'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $shop = fn ($query) => $query->where('company_id', $this->user()->company_id)->whereNull('deleted_at');
        $editing = $this->route('sale');
        $keepWalkIn = $editing instanceof Sale && (bool) $editing->customer?->is_system;
        $named = fn ($query) => $shop($query)->when(! $keepWalkIn, fn ($query) => $query->where('is_system', false));

        return [
            'customer_uuid' => ['required', 'uuid', Rule::exists('customers', 'uuid')->where($named)],
            'tray_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('stock_trays', 'uuid')->where(fn ($query) => $query->where('company_id', $this->user()->company_id)->where('is_active', true)),
            ],
            'order_uuid' => ['nullable', 'uuid', Rule::exists('advance_orders', 'uuid')->where('company_id', $this->user()->company_id)],
            'item_ids' => ['array'],
            'item_ids.*' => ['uuid', 'distinct'],
            'new_piece' => ['nullable', 'array'],
            'new_piece.name' => ['nullable', 'string', 'max:160'],
            'new_piece.category_uuid' => ['nullable', 'uuid', Rule::exists('categories', 'uuid')->where($shop)],
            'new_piece.metal_uuid' => ['required_with:new_piece.name', 'uuid', Rule::exists('metal_types', 'uuid')->where($shop)],
            'new_piece.purity_uuid' => ['required_with:new_piece.name', 'uuid', Rule::exists('purities', 'uuid')->where($shop)],
            'new_piece.location_uuid' => ['required_with:new_piece.name', 'uuid', Rule::exists('stock_locations', 'uuid')->where($shop)],
            'new_piece.gross_weight' => ['required_with:new_piece.name', 'numeric', 'gt:0', Limits::WEIGHT],
            'new_piece.stone_weight' => ['nullable', 'numeric', 'gte:0', Limits::WEIGHT],
            'new_piece.other_weight' => ['nullable', 'numeric', 'gte:0', Limits::WEIGHT],
            'new_piece.making_method_uuid' => ['nullable', 'uuid', Rule::exists('charge_methods', 'uuid')->where(fn ($query) => $shop($query)->where('applies_to', ChargeAppliesTo::Making->value))],
            'new_piece.making_value' => ['nullable', 'numeric', 'gte:0', Limits::MONEY],
            'new_piece.wastage_method_uuid' => ['nullable', 'uuid', Rule::exists('charge_methods', 'uuid')->where(fn ($query) => $shop($query)->where('applies_to', ChargeAppliesTo::Wastage->value))],
            'new_piece.wastage_value' => ['nullable', 'numeric', 'gte:0', Limits::MONEY],
            'new_piece.stone_value' => ['nullable', 'numeric', 'gte:0', Limits::MONEY],
            'new_piece.huid' => ['nullable', 'string', 'max:32'],
            'new_piece.own' => ['nullable', 'boolean'],
            'new_piece.stones' => ['array'],
            'new_piece.stones.*.name' => ['required', 'string', 'max:80'],
            'new_piece.stones.*.weight' => ['required', 'numeric', 'gte:0', Limits::WEIGHT],
            'new_piece.stones.*.value' => ['required', 'numeric', 'gte:0', Limits::MONEY],
            'new_piece.stones.*.rate' => ['nullable', 'numeric', 'gte:0', Limits::MONEY],
            'new_piece.stones.*.rate_unit' => ['nullable', Rule::in(StoneRate::UNITS)],
            'new_pieces' => ['array'],
            'new_pieces.*.name' => ['required', 'string', 'max:160'],
            'new_pieces.*.category_uuid' => ['nullable', 'uuid', Rule::exists('categories', 'uuid')->where($shop)],
            'new_pieces.*.metal_uuid' => ['required', 'uuid', Rule::exists('metal_types', 'uuid')->where($shop)],
            'new_pieces.*.purity_uuid' => ['required', 'uuid', Rule::exists('purities', 'uuid')->where($shop)],
            'new_pieces.*.location_uuid' => ['required', 'uuid', Rule::exists('stock_locations', 'uuid')->where($shop)],
            'new_pieces.*.gross_weight' => ['required', 'numeric', 'gt:0', Limits::WEIGHT],
            'new_pieces.*.stone_weight' => ['nullable', 'numeric', 'gte:0', Limits::WEIGHT],
            'new_pieces.*.other_weight' => ['nullable', 'numeric', 'gte:0', Limits::WEIGHT],
            'new_pieces.*.making_method_uuid' => ['nullable', 'uuid', Rule::exists('charge_methods', 'uuid')->where(fn ($query) => $shop($query)->where('applies_to', ChargeAppliesTo::Making->value))],
            'new_pieces.*.making_value' => ['nullable', 'numeric', 'gte:0', Limits::MONEY],
            'new_pieces.*.wastage_method_uuid' => ['nullable', 'uuid', Rule::exists('charge_methods', 'uuid')->where(fn ($query) => $shop($query)->where('applies_to', ChargeAppliesTo::Wastage->value))],
            'new_pieces.*.wastage_value' => ['nullable', 'numeric', 'gte:0', Limits::MONEY],
            'new_pieces.*.stone_value' => ['nullable', 'numeric', 'gte:0', Limits::MONEY],
            'new_pieces.*.huid' => ['nullable', 'string', 'max:32'],
            'new_pieces.*.own' => ['nullable', 'boolean'],
            'new_pieces.*.rate_per_gram' => ['nullable', 'numeric', 'gt:0', Limits::RATE],
            'new_pieces.*.stones' => ['array'],
            'new_pieces.*.stones.*.name' => ['required', 'string', 'max:80'],
            'new_pieces.*.stones.*.weight' => ['required', 'numeric', 'gte:0', Limits::WEIGHT],
            'new_pieces.*.stones.*.value' => ['required', 'numeric', 'gte:0', Limits::MONEY],
            'new_pieces.*.stones.*.rate' => ['nullable', 'numeric', 'gte:0', Limits::MONEY],
            'new_pieces.*.stones.*.rate_unit' => ['nullable', Rule::in(StoneRate::UNITS)],
            'discount' => ['required', 'numeric', 'gte:0', Limits::MONEY],
            'making_mode' => ['nullable', Rule::in(['inside', 'separate', 'processing'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'payments' => ['array'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'numeric', 'gt:0', Limits::MONEY],
            'payments.*.reference' => ['nullable', 'string', 'max:80'],
            'use_credit' => ['nullable', 'numeric', 'gte:0', Limits::MONEY],
            'include_previous' => ['sometimes', 'boolean'],
            'sold_at' => ['required', 'date'],
            'retail_items' => ['array'],
            'retail_items.*.name' => ['required', 'string', 'max:160'],
            'retail_items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'retail_items.*.unit_price' => ['required', 'numeric', 'gt:0', Limits::MONEY],
            'retail_items.*.tax_mode' => ['required', Rule::enum(RetailTaxMode::class)],
            'retail_items.*.tax_percent' => ['nullable', 'numeric', 'gte:0', 'max:100'],
            'retail_items.*.tax_amount' => ['nullable', 'numeric', 'gte:0', Limits::MONEY],
            'retail_items.*.hsn_code' => ['nullable', 'string', 'max:20'],
            'retail_items.*.product_uuid' => ['nullable', 'uuid', Rule::exists('retail_products', 'uuid')->where(fn ($query) => $shop($query)->where('is_active', true))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_uuid.required' => 'Choose the customer by name or mobile number, or add them as a new customer.',
            'customer_uuid.exists' => 'Choose the customer by name or mobile number, or add them as a new customer.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ids = $this->input('item_ids', []);
            $name = trim((string) data_get($this->input('new_piece'), 'name', ''));
            $pieces = $this->input('new_pieces', []);
            $retail = $this->input('retail_items', []);

            if ($ids === [] && $name === '' && $pieces === [] && $retail === []) {
                $validator->errors()->add('item_ids', 'Add jewellery, or an other item (name and price).');
            }

            $when = Carbon::parse((string) $this->input('sold_at'), config('app.timezone'));

            if ($when->isFuture()) {
                $validator->errors()->add('sold_at', 'Bill date and time cannot be in the future.');
            }
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function pieceRow(array $row): array
    {
        foreach (['stone_weight', 'other_weight', 'making_value', 'wastage_value', 'stone_value'] as $field) {
            if (! isset($row[$field]) || $row[$field] === '') {
                $row[$field] = '0';
            }
        }

        foreach (['making_method_uuid', 'wastage_method_uuid', 'category_uuid'] as $field) {
            if (($row[$field] ?? '') === '') {
                $row[$field] = null;
            }
        }

        $row['stones'] = $this->stoneRows($row['stones'] ?? []);
        $row['huid'] = $this->huid($row['huid'] ?? null);
        $row['own'] = filter_var($row['own'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $row;
    }

    private function huid(mixed $value): ?string
    {
        $value = Str::upper(trim((string) $value));

        return $value === '' ? null : $value;
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
