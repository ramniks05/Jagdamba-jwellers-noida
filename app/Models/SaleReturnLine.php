<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'sale_return_id', 'sale_line_id', 'item_id', 'amount'])]
class SaleReturnLine extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
