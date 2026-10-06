<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\SequenceResetPolicy;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use App\Support\TenantScopeKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'branch_id',
    'financial_year_id',
    'scope_key',
    'document_type',
    'prefix',
    'suffix',
    'separator',
    'padding',
    'next_number',
    'reset_policy',
    'last_period_key',
    'last_issued_number',
    'last_issued_at',
    'is_active',
    'is_system',
])]
class DocumentSequence extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'reset_policy' => SequenceResetPolicy::class,
            'padding' => 'integer',
            'next_number' => 'integer',
            'last_issued_at' => 'datetime',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (DocumentSequence $sequence): void {
            $sequence->scope_key = TenantScopeKey::forBranch($sequence->branch_id);
            $sequence->prefix = strtoupper((string) $sequence->prefix);

            if ($sequence->suffix) {
                $sequence->suffix = strtoupper((string) $sequence->suffix);
            }
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

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(DocumentNumberAllocation::class);
    }
}
