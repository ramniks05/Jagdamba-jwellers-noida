<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'code',
    'name',
    'legal_name',
    'logo_path',
    'email',
    'phone',
    'mobile',
    'website',
    'gstin',
    'pan',
    'address_line1',
    'address_line2',
    'city',
    'state',
    'postal_code',
    'country',
    'status',
    'timezone',
    'currency_code',
    'fy_start_month',
    'plan_code',
    'trial_ends_at',
    'subscription_ends_at',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => CompanyStatus::class,
            'fy_start_month' => 'integer',
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    public function financialYears(): HasMany
    {
        return $this->hasMany(FinancialYear::class);
    }

    public function currentFinancialYear(): HasOne
    {
        return $this->hasOne(FinancialYear::class)->where('is_current', true);
    }

    public function documentSequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }

    public function isOperational(): bool
    {
        return $this->status->isOperational();
    }

    public function displayName(): string
    {
        return $this->legal_name ?: $this->name;
    }

    public function formattedAddress(): string
    {
        return collect([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ])->filter()->implode(', ');
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->logo_path) {
                return null;
            }

            return Storage::disk('public')->url($this->logo_path);
        });
    }
}
