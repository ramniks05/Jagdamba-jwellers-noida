<?php

namespace App\Models;

use App\Models\Concerns\HasMasterRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'parent_id', 'code', 'name', 'sort_order', 'is_active'])]
class Category extends Model
{
    use HasMasterRecord;

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

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
        if ($this->children()->exists()) {
            return 'Remove the subcategories first.';
        }

        return $this->usageBlocker('category', [
            'items' => ['category_id', 'piece'],
            'designs' => ['category_id', 'design'],
        ]);
    }
}
