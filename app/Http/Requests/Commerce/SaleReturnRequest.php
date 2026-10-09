<?php

namespace App\Http\Requests\Commerce;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;

class SaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sale = $this->route('sale');

        return $sale instanceof Sale && (bool) $this->user()?->can('create', SaleReturn::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'lines' => array_values(array_filter((array) $this->input('lines', []))),
            'refund' => $this->filled('refund') ? $this->input('refund') : '0',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*' => ['required', 'uuid'],
            'refund' => ['required', 'numeric', 'gte:0', Limits::MONEY],
        ];
    }
}
