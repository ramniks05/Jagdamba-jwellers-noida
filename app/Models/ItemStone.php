<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'item_id', 'name', 'weight', 'value', 'position'])]
class ItemStone extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
            'value' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
