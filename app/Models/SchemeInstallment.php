<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'scheme_enrollment_id', 'payment_id', 'due_on', 'amount', 'paid_at'])]
class SchemeInstallment extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(SchemeEnrollment::class, 'scheme_enrollment_id');
    }
}
