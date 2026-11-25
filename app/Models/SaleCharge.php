<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * An extra amount added to a bill, such as a card charge or packing. It has no GST.
 */
#[Fillable(['company_id', 'sale_id', 'label', 'amount', 'position'])]
class SaleCharge extends Model
{
    use BelongsToCompany, HasPublicUuid;

    /**
     * False until the sale_charges migration has run, so billing keeps working on a server
     * that has new code but has not been migrated yet.
     */
    public static function ready(): bool
    {
        return Schema::hasTable('sale_charges');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
