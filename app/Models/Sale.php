<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'branch_id', 'customer_id', 'number', 'status', 'sold_at',
    'lines_amount', 'discount_amount', 'taxable_amount', 'tax_percent', 'tax_amount',
    'prices_include_tax', 'round_off', 'total', 'paid_amount', 'notes', 'user_id',
])]
class Sale extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'lines_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'tax_percent' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'prices_include_tax' => 'boolean',
            'round_off' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function balanceDue(): string
    {
        return (string) BigDecimal::of((string) $this->total)
            ->minus((string) $this->paid_amount)
            ->toScale(2, RoundingMode::HalfUp);
    }
}
