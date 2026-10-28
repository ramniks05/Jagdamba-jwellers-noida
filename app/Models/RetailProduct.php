<?php

namespace App\Models;

use App\Enums\RetailTaxMode;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'code', 'name', 'default_unit_price', 'default_tax_mode', 'default_tax_percent',
    'hsn_code', 'sort_order', 'is_active',
])]
class RetailProduct extends Model
{
    use BelongsToCompany, HasPublicUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'default_unit_price' => 'decimal:2',
            'default_tax_mode' => RetailTaxMode::class,
            'default_tax_percent' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function saleLines(): HasMany
    {
        return $this->hasMany(SaleRetailLine::class);
    }
}
