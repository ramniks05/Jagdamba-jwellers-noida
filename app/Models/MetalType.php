<?php

namespace App\Models;

use App\Models\Concerns\HasMasterRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'code', 'name', 'hsn_code', 'sort_order', 'is_active'])]
class MetalType extends Model
{
    use HasMasterRecord;

    public function purities(): HasMany
    {
        return $this->hasMany(Purity::class);
    }

    public function deletionBlocker(): ?string
    {
        if ($this->purities()->exists()) {
            return 'Remove the purities for this metal first.';
        }

        return null;
    }
}
