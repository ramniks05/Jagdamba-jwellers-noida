<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'branch_id', 'supplier_id', 'number', 'purchased_at', 'lines_amount',
    'discount_amount', 'taxable_amount', 'tax_percent', 'tax_amount', 'round_off', 'total',
    'paid_amount', 'notes', 'user_id',
])]
class Purchase extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'lines_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'tax_percent' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'round_off' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }
}
