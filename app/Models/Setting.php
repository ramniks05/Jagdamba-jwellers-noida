<?php

namespace App\Models;

use App\Enums\SettingValueType;
use App\Models\Concerns\BelongsToCompany;
use App\Support\TenantScopeKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'branch_id',
    'scope_key',
    'group',
    'key',
    'value',
    'value_type',
])]
class Setting extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'value_type' => SettingValueType::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Setting $setting): void {
            $setting->scope_key = TenantScopeKey::forBranch($setting->branch_id);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function typedValue(): mixed
    {
        return match ($this->value_type) {
            SettingValueType::Boolean => $this->value === '1',
            SettingValueType::Integer => (int) $this->value,
            SettingValueType::Decimal => $this->value,
            SettingValueType::Json => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }
}
