<?php

namespace App\Models;

use App\Enums\InventoryMovement;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'item_id', 'branch_id', 'type', 'reference_type', 'reference_id',
    'quantity', 'gross_weight', 'net_weight', 'rate', 'value',
    'source_location_id', 'destination_location_id', 'user_id', 'notes', 'occurred_at',
])]
class InventoryTransaction extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'type' => InventoryMovement::class,
            'quantity' => 'decimal:3',
            'gross_weight' => 'decimal:3',
            'net_weight' => 'decimal:3',
            'rate' => 'decimal:2',
            'value' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
