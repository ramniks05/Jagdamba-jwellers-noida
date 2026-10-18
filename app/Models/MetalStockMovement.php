<?php

namespace App\Models;

use App\Enums\MetalStockKind;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'company_id', 'branch_id', 'metal_type_id', 'purity_id', 'kind', 'grams',
    'rate_per_gram', 'taxable_amount', 'tax_percent', 'tax_amount', 'total_amount', 'supplier_bill_number',
    'reference_type', 'reference_id', 'user_id', 'occurred_at',
])]
class MetalStockMovement extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'kind' => MetalStockKind::class,
            'grams' => 'decimal:3',
            'rate_per_gram' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
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
