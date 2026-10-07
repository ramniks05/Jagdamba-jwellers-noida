<?php

namespace App\Models;

use App\Enums\IntakeStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'customer_id', 'name', 'mobile', 'mobile_key', 'email', 'address_line1',
    'city', 'state', 'postal_code', 'country', 'pan', 'gstin', 'status', 'reviewed_at', 'reviewed_by',
])]
class CustomerIntake extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'status' => IntakeStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
