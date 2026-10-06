<?php

namespace App\Models;

use App\Enums\LocationKind;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'branch_id', 'parent_id', 'kind', 'code', 'name', 'is_active'])]
class StockLocation extends Model
{
    use BelongsToCompany, HasPublicUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'kind' => LocationKind::class,
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function label(): string
    {
        $prefix = $this->parent?->name;

        return $prefix ? $prefix.' / '.$this->name : $this->name;
    }
}
