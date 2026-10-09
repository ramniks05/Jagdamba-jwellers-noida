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

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function deletionBlocker(): ?string
    {
        return $this->usageBlocker('collection', [
            'items' => ['collection_id', 'piece'],
            'designs' => ['collection_id', 'design'],
        ]);
    }
}
