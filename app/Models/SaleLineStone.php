<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'sale_line_id', 'name', 'weight', 'value', 'position'])]
class SaleLineStone extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
            'value' => 'decimal:2',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class, 'sale_line_id');
    }
}
