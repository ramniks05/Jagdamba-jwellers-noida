<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'branch_id', 'customer_id', 'item_id', 'number', 'description', 'problem',
    'gross_weight', 'technician', 'estimated_cost', 'final_charge', 'expected_on', 'status', 'received_at',
    'delivered_at', 'notes', 'user_id',
])]
class RepairOrder extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'gross_weight' => 'decimal:3',
            'estimated_cost' => 'decimal:2',
            'final_charge' => 'decimal:2',
            'expected_on' => 'date',
            'received_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public const OPEN = ['received', 'inspection', 'repairing', 'ready'];

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'received' => 'Received',
            'inspection' => 'Inspection',
            'repairing' => 'Repairing',
            'ready' => 'Ready to deliver',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => ucfirst((string) $this->status),
        };
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
