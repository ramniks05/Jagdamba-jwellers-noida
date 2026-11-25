<?php

namespace App\Http\Requests\Commerce;

class SupplierPaymentRequest extends ReceiptRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'paid_on' => ['nullable', 'date'],
        ];
    }
}
