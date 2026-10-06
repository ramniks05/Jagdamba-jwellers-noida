<?php

namespace App\Models;

use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'company_id', 'party_type', 'party_id', 'direction', 'amount', 'narration',
    'reference_type', 'reference_id', 'occurred_at', 'user_id',
])]
class LedgerEntry extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'party_type' => PartyType::class,
            'direction' => LedgerDirection::class,
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }
}
