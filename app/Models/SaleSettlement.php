<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleSettlement extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'sale_id',
        'settled_sale_id',
        'previous_paid_amount',
    ];

    protected function casts(): array
    {
        return [
            'previous_paid_amount' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function settledSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'settled_sale_id');
    }
}
