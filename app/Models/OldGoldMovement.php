<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'branch_id', 'metal_type_id', 'purity_id', 'direction', 'kind', 'gross_weight', 'fine_weight',
    'value', 'amount_received', 'party', 'notes', 'old_gold_exchange_id', 'item_id', 'moved_at', 'user_id',
])]
class OldGoldMovement extends Model
{
    use BelongsToCompany, HasPublicUuid;

    public const SEND_KINDS = [
        'refiner' => 'Sent to refiner / melted',
        'karigar' => 'Given to karigar',
        'dealer' => 'Sold to bullion dealer',
        'adjustment' => 'Weight correction',
    ];

    protected function casts(): array
    {
        return [
            'gross_weight' => 'decimal:3',
            'fine_weight' => 'decimal:3',
            'value' => 'decimal:2',
            'amount_received' => 'decimal:2',
            'moved_at' => 'datetime',
        ];
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'exchange' => 'Old gold exchange',
            'piece' => 'Made into a stock piece',
            default => self::SEND_KINDS[$this->kind] ?? ucfirst((string) $this->kind),
        };
    }

    public function metalType(): BelongsTo
    {
        return $this->belongsTo(MetalType::class);
    }

    public function purity(): BelongsTo
    {
        return $this->belongsTo(Purity::class);
    }

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(OldGoldExchange::class, 'old_gold_exchange_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
