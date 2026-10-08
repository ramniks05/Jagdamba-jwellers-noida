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
    'company_id', 'branch_id', 'customer_id', 'metal_type_id', 'purity_id', 'metal_rate_id', 'sale_id',
    'number', 'description', 'design_notes', 'expected_weight', 'rate_per_gram', 'estimated_making',
    'advance_paid', 'advance_refunded', 'status', 'booked_at', 'due_on', 'ready_at', 'delivered_at',
    'cancelled_at', 'notes', 'user_id',
])]
class AdvanceOrder extends Model
{
    use BelongsToCompany, HasPublicUuid;

    public const OPEN = ['booked', 'ready'];

    protected function casts(): array
    {
        return [
            'expected_weight' => 'decimal:3',
            'rate_per_gram' => 'decimal:2',
            'estimated_making' => 'decimal:2',
            'advance_paid' => 'decimal:2',
            'advance_refunded' => 'decimal:2',
            'booked_at' => 'datetime',
            'due_on' => 'date',
            'ready_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'booked' => 'Booked',
            'ready' => 'Ready to deliver',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => ucfirst((string) $this->status),
        };
    }

    public function goldEstimate(): string
    {
        return (string) BigDecimal::of((string) $this->expected_weight)
            ->multipliedBy((string) $this->rate_per_gram)
            ->toScale(2, RoundingMode::HalfUp);
    }

    public function advanceHeld(): string
    {
        return (string) BigDecimal::of((string) $this->advance_paid)
            ->minus((string) $this->advance_refunded)
            ->toScale(2, RoundingMode::HalfUp);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
