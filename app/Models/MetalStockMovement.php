<?php

namespace App\Models;

use App\Enums\MetalStockKind;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'company_id', 'branch_id', 'metal_type_id', 'purity_id', 'kind', 'grams',
    'reference_type', 'reference_id', 'user_id', 'occurred_at',
])]
class MetalStockMovement extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'kind' => MetalStockKind::class,
            'grams' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
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

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
