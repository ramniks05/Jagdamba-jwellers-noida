<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'gold_scheme_id', 'customer_id', 'number', 'started_on', 'status',
    'matured_at', 'maturity_amount',
])]
class SchemeEnrollment extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'matured_at' => 'datetime',
            'maturity_amount' => 'decimal:2',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(GoldScheme::class, 'gold_scheme_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(SchemeInstallment::class);
    }
}
