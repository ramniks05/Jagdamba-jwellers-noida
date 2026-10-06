<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'branch_id', 'customer_id', 'metal_type_id', 'purity_id', 'number', 'description',
    'gross_weight', 'stone_weight', 'net_weight', 'rate_per_gram', 'gold_value', 'loan_mode',
    'loan_percent', 'principal', 'interest_percent', 'interest_charged', 'status', 'pledged_at',
    'interest_from', 'released_at', 'notes', 'user_id',
])]
class GirviPledge extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'gross_weight' => 'decimal:3',
            'stone_weight' => 'decimal:3',
            'net_weight' => 'decimal:3',
            'rate_per_gram' => 'decimal:2',
            'gold_value' => 'decimal:2',
            'loan_percent' => 'decimal:4',
            'principal' => 'decimal:2',
            'interest_percent' => 'decimal:4',
            'interest_charged' => 'decimal:2',
            'pledged_at' => 'datetime',
            'interest_from' => 'date',
            'released_at' => 'datetime',
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
        return $this->hasMany(Payment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GirviPledgeItem::class);
    }
}
