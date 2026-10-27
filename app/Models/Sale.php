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
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'company_id', 'branch_id', 'customer_id', 'stock_tray_id', 'number', 'status', 'sold_at', 'cancelled_at', 'cancelled_by_user_id', 'cancel_reason',
    'lines_amount', 'discount_amount', 'taxable_amount', 'tax_percent', 'tax_amount',
    'making_mode', 'making_amount', 'making_tax_percent', 'making_tax_amount', 'hallmark_amount', 'prices_include_tax', 'round_off', 'total', 'paid_amount', 'advance_amount', 'credit_amount', 'previous_due', 'previous_bills', 'notes', 'user_id',
])]
class Sale extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'lines_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'tax_percent' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'making_amount' => 'decimal:2',
            'making_tax_percent' => 'decimal:4',
            'making_tax_amount' => 'decimal:2',
            'hallmark_amount' => 'decimal:2',
            'prices_include_tax' => 'boolean',
            'round_off' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'advance_amount' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'previous_due' => 'decimal:2',
            'previous_bills' => 'integer',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function advanceOrder(): HasOne
    {
        return $this->hasOne(AdvanceOrder::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(SaleSettlement::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function stockTray(): BelongsTo
    {
        return $this->belongsTo(StockTray::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function makingLabel(): string
    {
        return in_array($this->making_mode, ['processing', 'separate'], true) ? 'Processing charge' : 'Making';
    }

    /**
     * Hallmark kept out of GST on this bill. Bills saved before that rule return zero, as their Hallmark was taxed.
     */
    public function untaxedHallmark(): string
    {
        return (string) BigDecimal::of((string) ($this->hallmark_amount ?? '0'))->toScale(2, RoundingMode::HalfUp);
    }

    public function extraPaid(): string
    {
        $received = $this->payments->reduce(fn (BigDecimal $sum, Payment $payment) => $sum->plus((string) $payment->amount), BigDecimal::zero())
            ->plus((string) ($this->advance_amount ?? 0))
            ->plus((string) ($this->credit_amount ?? 0));
        $extra = $received->minus((string) $this->total)->minus((string) ($this->previous_due ?? 0));

        return (string) ($extra->isPositive() ? $extra : BigDecimal::zero())->toScale(2, RoundingMode::HalfUp);
    }

    public function balanceDue(): string
    {
        return (string) BigDecimal::of((string) $this->total)
            ->plus((string) ($this->previous_due ?? 0))
            ->minus((string) $this->paid_amount)
            ->toScale(2, RoundingMode::HalfUp);
    }
}
