<?php

namespace App\Models;

use App\Enums\KycStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'code', 'name', 'contact_name', 'mobile', 'email', 'address_line1',
    'address_line2', 'city', 'state', 'postal_code', 'country', 'pan', 'gstin',
    'bank_name', 'account_number', 'ifsc', 'kyc_status', 'is_active', 'notes',
])]
class Supplier extends Model
{
    use BelongsToCompany, HasPublicUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'kyc_status' => KycStatus::class,
            'is_active' => 'boolean',
        ];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
