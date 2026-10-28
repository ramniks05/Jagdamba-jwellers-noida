<?php

namespace App\Http\Requests\Commerce;

use App\Enums\RetailTaxMode;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RetailProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $shop = fn ($query) => $query->where('company_id', $this->user()->company_id)->whereNull('deleted_at');
        $product = $this->route('retail_product');

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('retail_products', 'code')->where($shop)->ignore($product),
            ],
            'name' => ['required', 'string', 'max:160'],
            'default_unit_price' => ['nullable', 'numeric', 'gte:0', Limits::MONEY],
            'default_tax_mode' => ['required', Rule::enum(RetailTaxMode::class)],
            'default_tax_percent' => ['nullable', 'numeric', 'gte:0', 'max:100'],
            'hsn_code' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
