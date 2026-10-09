<?php

namespace App\Models;

use App\Models\Concerns\HasMasterRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'metal_type_id', 'code', 'name', 'fineness', 'sort_order', 'is_active'])]
class Purity extends Model
{
    use HasMasterRecord;

    protected function casts(): array
    {
        return [
            'fineness' => 'decimal:6',
        ];
    }

    public function metalType(): BelongsTo
    {
        return $this->belongsTo(MetalType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function deletionBlocker(): ?string
    {
        return $this->usageBlocker('purity', [
            'items' => ['purity_id', 'piece'],
            'metal_rates' => ['purity_id', 'metal rate'],
            'girvi_pledges' => ['purity_id', 'girvi loan'],
            'girvi_pledge_items' => ['purity_id', 'girvi piece'],
            'advance_orders' => ['purity_id', 'customer order'],
            'old_gold_exchanges' => ['purity_id', 'old gold entry'],
            'old_gold_movements' => ['purity_id', 'old gold movement'],
        ]);
    }
}
