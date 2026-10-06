<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

#[Fillable([
    'company_id', 'branch_id', 'metal_type_id', 'purity_id', 'rate_per_gram',
    'effective_at', 'source', 'user_id', 'note',
])]
class MetalRate extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('A metal rate is historical and cannot be changed.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('A metal rate is historical and cannot be removed.');
        });
    }

    protected function casts(): array
    {
        return [
            'rate_per_gram' => 'decimal:2',
            'effective_at' => 'datetime',
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
}
