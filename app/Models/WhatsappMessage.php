<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'sale_id', 'recipient', 'template', 'provider_id', 'status', 'error', 'user_id', 'status_at'])]
class WhatsappMessage extends Model
{
    use BelongsToCompany, HasPublicUuid;

    public const STATUS_RANK = ['failed' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

    protected function casts(): array
    {
        return [
            'status_at' => 'datetime',
        ];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'sent' => 'Sent',
            'delivered' => 'Delivered',
            'read' => 'Read by customer',
            'failed' => 'Failed',
            default => ucfirst((string) $this->status),
        };
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
