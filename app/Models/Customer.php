<?php

namespace App\Models;

use App\Enums\CustomerType;
use App\Enums\KycStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'code', 'name', 'mobile', 'email', 'address_line1', 'address_line2',
    'city', 'state', 'postal_code', 'country', 'dob', 'anniversary', 'pan', 'gstin',
    'id_proof_type', 'id_proof_number', 'kyc_status', 'customer_type', 'is_system',
    'is_active', 'notes',
])]
class Customer extends Model
{
    use BelongsToCompany, HasPublicUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'anniversary' => 'date',
            'kyc_status' => KycStatus::class,
            'customer_type' => CustomerType::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
