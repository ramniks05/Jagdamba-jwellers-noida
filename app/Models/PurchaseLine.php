<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'company_id', 'purchase_id', 'item_id', 'name', 'item_code', 'gross_weight', 'net_weight', 'rate_per_gram',
    'wastage_percent', 'labour_per_gram', 'stone_amount', 'line_amount', 'cost_amount',
])]
class PurchaseLine extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'gross_weight' => 'decimal:3',
            'net_weight' => 'decimal:3',
            'rate_per_gram' => 'decimal:2',
            'wastage_percent' => 'decimal:3',
            'labour_per_gram' => 'decimal:2',
            'stone_amount' => 'decimal:2',
            'line_amount' => 'decimal:2',
            'cost_amount' => 'decimal:2',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function returnLine(): HasOne
    {
        return $this->hasOne(PurchaseReturnLine::class);
    }
}
