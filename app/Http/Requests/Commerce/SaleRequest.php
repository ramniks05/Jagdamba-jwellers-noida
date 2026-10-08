<?php

namespace App\Http\Requests\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\PaymentMethod;
use App\Models\Sale;
use App\Support\StoneRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        }

        $pieces = [];

        foreach ((array) $this->input('new_pieces', []) as $row) {
            if (! is_array($row) || trim((string) ($row['name'] ?? '')) === '') {
                continue;
            }

            $pieces[] = $this->pieceRow($row);
        }

        $this->merge([
            'payments' => $payments,
            'item_ids' => array_values(array_filter((array) $this->input('item_ids', []))),
            'new_piece' => $piece,
            'new_pieces' => $pieces,
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
            'order_uuid' => ['nullable', 'uuid', Rule::exists('advance_orders', 'uuid')->where('company_id', $this->user()->company_id)],
            'item_ids' => ['array'],
            'item_ids.*' => ['uuid', 'distinct'],
            'new_piece' => ['nullable', 'array'],
            'new_piece.name' => ['nullable', 'string', 'max:160'],
            'new_piece.metal_uuid' => ['required_with:new_piece.name', 'uuid', Rule::exists('metal_types', 'uuid')->where($shop)],
            'new_piece.purity_uuid' => ['required_with:new_piece.name', 'uuid', Rule::exists('purities', 'uuid')->where($shop)],
            'new_piece.location_uuid' => ['required_with:new_piece.name', 'uuid', Rule::exists('stock_locations', 'uuid')->where($shop)],
            'new_piece.gross_weight' => ['required_with:new_piece.name', 'numeric', 'gt:0'],
            'new_piece.stone_weight' => ['nullable', 'numeric', 'gte:0'],
            'new_piece.other_weight' => ['nullable', 'numeric', 'gte:0'],
            'new_piece.making_method_uuid' => ['nullable', 'uuid', Rule::exists('charge_methods', 'uuid')->where(fn ($query) => $shop($query)->where('applies_to', ChargeAppliesTo::Making->value))],
            'new_piece.making_value' => ['nullable', 'numeric', 'gte:0'],
            'new_piece.wastage_method_uuid' => ['nullable', 'uuid', Rule::exists('charge_methods', 'uuid')->where(fn ($query) => $shop($query)->where('applies_to', ChargeAppliesTo::Wastage->value))],
            'new_piece.wastage_value' => ['nullable', 'numeric', 'gte:0'],
            'new_piece.stone_value' => ['nullable', 'numeric', 'gte:0'],
            'new_piece.stones' => ['array'],
            'new_piece.stones.*.name' => ['required', 'string', 'max:80'],
            'new_piece.stones.*.weight' => ['required', 'numeric', 'gte:0'],
            'new_piece.stones.*.value' => ['required', 'numeric', 'gte:0'],
            'new_piece.stones.*.rate' => ['nullable', 'numeric', 'gte:0'],
            'new_piece.stones.*.rate_unit' => ['nullable', Rule::in(StoneRate::UNITS)],
            'new_pieces' => ['array'],
            'new_pieces.*.name' => ['required', 'string', 'max:160'],
            'new_pieces.*.metal_uuid' => ['required', 'uuid', Rule::exists('metal_types', 'uuid')->where($shop)],
            'new_pieces.*.purity_uuid' => ['required', 'uuid', Rule::exists('purities', 'uuid')->where($shop)],
            'new_pieces.*.location_uuid' => ['required', 'uuid', Rule::exists('stock_locations', 'uuid')->where($shop)],
            'new_pieces.*.gross_weight' => ['required', 'numeric', 'gt:0'],
            'new_pieces.*.stone_weight' => ['nullable', 'numeric', 'gte:0'],
            'new_pieces.*.other_weight' => ['nullable', 'numeric', 'gte:0'],
            'new_pieces.*.making_method_uuid' => ['nullable', 'uuid', Rule::exists('charge_methods', 'uuid')->where(fn ($query) => $shop($query)->where('applies_to', ChargeAppliesTo::Making->value))],
            'new_pieces.*.making_value' => ['nullable', 'numeric', 'gte:0'],
            'new_pieces.*.wastage_method_uuid' => ['nullable', 'uuid', Rule::exists('charge_methods', 'uuid')->where(fn ($query) => $shop($query)->where('applies_to', ChargeAppliesTo::Wastage->value))],
            'new_pieces.*.wastage_value' => ['nullable', 'numeric', 'gte:0'],
            'new_pieces.*.stone_value' => ['nullable', 'numeric', 'gte:0'],
            'new_pieces.*.stones' => ['array'],
            'new_pieces.*.stones.*.name' => ['required', 'string', 'max:80'],
            'new_pieces.*.stones.*.weight' => ['required', 'numeric', 'gte:0'],
            'new_pieces.*.stones.*.value' => ['required', 'numeric', 'gte:0'],
            'new_pieces.*.stones.*.rate' => ['nullable', 'numeric', 'gte:0'],
            'new_pieces.*.stones.*.rate_unit' => ['nullable', Rule::in(StoneRate::UNITS)],
            'discount' => ['required', 'numeric', 'gte:0'],
            'making_mode' => ['nullable', Rule::in(['inside', 'separate', 'processing'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'payments' => ['array'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:80'],
            'use_credit' => ['nullable', 'numeric', 'gte:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ids = $this->input('item_ids', []);
            $name = trim((string) data_get($this->input('new_piece'), 'name', ''));
            $pieces = $this->input('new_pieces', []);

            if ($ids === [] && $name === '' && $pieces === []) {
                $validator->errors()->add('item_ids', 'Choose a piece in stock, or add a new piece on this bill.');
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

        foreach (['making_method_uuid', 'wastage_method_uuid'] as $field) {
            if (($row[$field] ?? '') === '') {
                $row[$field] = null;
            }
        }

        $row['stones'] = $this->stoneRows($row['stones'] ?? []);

        return $row;
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
