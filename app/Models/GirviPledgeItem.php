<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'girvi_pledge_id', 'metal_type_id', 'purity_id', 'description',
    'gross_weight', 'stone_weight', 'net_weight', 'rate_per_gram', 'gold_value',
])]
class GirviPledgeItem extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'gross_weight' => 'decimal:3',
            'stone_weight' => 'decimal:3',
            'net_weight' => 'decimal:3',
            'rate_per_gram' => 'decimal:2',
            'gold_value' => 'decimal:2',
        ];
    }

    public function pledge(): BelongsTo
    {
        return $this->belongsTo(GirviPledge::class, 'girvi_pledge_id');
    }

    public function metalType(): BelongsTo
    {
        return $this->belongsTo(MetalType::class);
    }

    public function purity(): BelongsTo
    {
        return $this->belongsTo(Purity::class);
    }
}
