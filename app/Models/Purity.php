<?php

namespace App\Models;

use App\Models\Concerns\HasMasterRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'metal_type_id', 'code', 'name', 'fineness', 'sort_order', 'is_active'])]
class Purity extends Model
{
    use HasMasterRecord;

    protected function casts(): array
    {
        return [
            'fineness' => 'decimal:6',
        ];
    }

    public function metalType(): BelongsTo
    {
        return $this->belongsTo(MetalType::class);
    }
}
