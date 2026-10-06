<?php

namespace App\Models;

use App\Models\Concerns\HasMasterRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'code', 'name', 'sort_order', 'is_active'])]
class Collection extends Model
{
    use HasMasterRecord;

    public function designs(): HasMany
    {
        return $this->hasMany(Design::class);
    }

    public function deletionBlocker(): ?string
    {
        if ($this->designs()->exists()) {
            return 'This collection is used by a design.';
        }

        return null;
    }
}
