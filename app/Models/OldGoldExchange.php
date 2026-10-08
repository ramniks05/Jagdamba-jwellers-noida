<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'branch_id', 'customer_id', 'metal_type_id', 'purity_id', 'number', 'exchanged_at',
    'gross_weight', 'stone_weight', 'net_weight', 'melted_weight', 'melting_loss_percent', 'rate_per_gram',
    'deduction_amount', 'exchange_value', 'testing_result', 'notes', 'user_id',
])]
class OldGoldExchange extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'exchanged_at' => 'datetime',
            'gross_weight' => 'decimal:3',
            'stone_weight' => 'decimal:3',
            'net_weight' => 'decimal:3',
            'melted_weight' => 'decimal:3',
            'melting_loss_percent' => 'decimal:4',
            'rate_per_gram' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'exchange_value' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function metalType(): BelongsTo
    {
        return $this->belongsTo(MetalType::class);
    }

    public function purity(): BelongsTo
    {
        return $this->belongsTo(Purity::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    public function pieceMovements(): HasMany
    {
        return $this->hasMany(OldGoldMovement::class)->where('kind', 'piece')->orderBy('id');
    }
}
