<?php

namespace App\Models;

use App\Enums\RetailTaxMode;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'sale_id', 'retail_product_id', 'name', 'quantity', 'unit_price', 'tax_mode',
    'tax_percent', 'taxable_amount', 'tax_amount', 'line_amount', 'hsn_code', 'position',
])]
class SaleRetailLine extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'tax_mode' => RetailTaxMode::class,
            'tax_percent' => 'decimal:4',
            'taxable_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_amount' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(RetailProduct::class, 'retail_product_id');
    }
}
