<?php

namespace App\Services\Commerce;

use App\Enums\RetailTaxMode;
use App\Models\Company;
use App\Models\RetailProduct;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RetailProductService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, array $attributes): RetailProduct
    {
        return RetailProduct::query()->create($this->payload($company, $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(RetailProduct $product, array $attributes): RetailProduct
    {
        $product->fill($this->payload($product->company, $attributes, $product));
        $product->save();

        return $product;
    }

    public function delete(RetailProduct $product): void
    {
        if ($product->saleLines()->exists()) {
            throw ValidationException::withMessages([
                'code' => 'This item was sold on a bill, so it can be hidden but not removed.',
            ]);
        }

        $product->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function payload(Company $company, array $attributes, ?RetailProduct $existing = null): array
    {
        $code = Str::upper(trim((string) ($attributes['code'] ?? '')));
        $mode = RetailTaxMode::tryFrom((string) ($attributes['default_tax_mode'] ?? 'none')) ?? RetailTaxMode::None;

        return [
            'company_id' => $company->id,
            'code' => $code,
            'name' => trim((string) ($attributes['name'] ?? '')),
            'default_unit_price' => $this->money($attributes['default_unit_price'] ?? null),
            'default_tax_mode' => $mode,
            'default_tax_percent' => $mode === RetailTaxMode::None ? null : $this->percent($attributes['default_tax_percent'] ?? null),
            'hsn_code' => $this->optional($attributes['hsn_code'] ?? null),
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            'is_active' => filter_var($attributes['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ] + ($existing ? [] : ['uuid' => (string) Str::uuid()]);
    }

    private function money(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));

        return $raw === '' ? null : number_format((float) $raw, 2, '.', '');
    }

    private function percent(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));

        return $raw === '' ? null : number_format((float) $raw, 4, '.', '');
    }

    private function optional(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));

        return $raw === '' ? null : $raw;
    }
}
