<?php

namespace App\Models;

use App\Enums\BranchStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id',
    'code',
    'name',
    'is_head_office',
    'email',
    'phone',
    'mobile',
    'gstin',
    'address_line1',
    'address_line2',
    'city',
    'state',
    'postal_code',
    'country',
    'status',
    'timezone',
])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use BelongsToCompany, HasFactory, HasPublicUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_head_office' => 'boolean',
            'status' => BranchStatus::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @param  Builder<Branch>  $query
     * @return Builder<Branch>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $ids = $user->restrictedBranchIds();

        if ($ids !== null) {
            $query->whereIn($query->qualifyColumn('id'), $ids);
        }

        return $query;
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
}
