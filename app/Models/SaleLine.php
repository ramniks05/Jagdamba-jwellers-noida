<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'sale_id', 'item_id', 'metal_rate_id', 'name', 'item_code', 'metal_name',
    'purity_name', 'gross_weight', 'stone_weight', 'other_weight', 'net_weight', 'rate_per_gram',
    'making_method', 'making_value', 'wastage_method', 'wastage_value', 'metal_amount',
    'wastage_amount', 'making_amount', 'stone_amount', 'line_amount',
])]
class SaleLine extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'gross_weight' => 'decimal:3',
            'stone_weight' => 'decimal:3',
            'other_weight' => 'decimal:3',
            'net_weight' => 'decimal:3',
            'rate_per_gram' => 'decimal:2',
            'making_value' => 'decimal:4',
            'wastage_value' => 'decimal:4',
            'metal_amount' => 'decimal:2',
            'wastage_amount' => 'decimal:2',
            'making_amount' => 'decimal:2',
            'stone_amount' => 'decimal:2',
            'line_amount' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
