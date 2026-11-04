<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'advance_order_id', 'metal_type_id', 'purity_id', 'metal_rate_id',
    'expected_weight', 'rate_per_gram', 'estimated_making', 'sort_order',
])]
class AdvanceOrderLine extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'expected_weight' => 'decimal:3',
            'rate_per_gram' => 'decimal:2',
            'estimated_making' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(AdvanceOrder::class, 'advance_order_id');
    }

    public function metalType(): BelongsTo
    {
        return $this->belongsTo(MetalType::class);
    }

    public function purity(): BelongsTo
    {
        return $this->belongsTo(Purity::class);
    }

    public function metalRate(): BelongsTo
    {
        return $this->belongsTo(MetalRate::class);
    }

    public function goldEstimate(): string
    {
        return (string) BigDecimal::of((string) $this->expected_weight)
            ->multipliedBy((string) $this->rate_per_gram)
            ->toScale(2, RoundingMode::HalfUp);
    }

    public function metalLabel(): string
    {
        return trim(($this->metalType?->name ?? '').' '.($this->purity?->name ?? ''));
    }
}
