<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'code', 'name', 'installment_mode', 'monthly_amount', 'duration_months',
    'bonus_type', 'bonus_value', 'is_active',
])]
class GoldScheme extends Model
{
    use BelongsToCompany, HasPublicUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'bonus_value' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(SchemeEnrollment::class);
    }
}
