<?php

namespace App\Models;

use App\Enums\TrayMovementKind;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'company_id', 'stock_tray_id', 'kind', 'grams_delta', 'pieces_delta',
    'reference_type', 'reference_id', 'notes', 'user_id', 'occurred_at',
])]
class TrayMovement extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'kind' => TrayMovementKind::class,
            'grams_delta' => 'decimal:3',
            'pieces_delta' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function tray(): BelongsTo
    {
        return $this->belongsTo(StockTray::class, 'stock_tray_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
