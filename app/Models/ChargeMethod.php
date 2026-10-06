<?php

namespace App\Models;

use App\Enums\ChargeAppliesTo;
use App\Models\Concerns\HasMasterRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'applies_to', 'code', 'name', 'sort_order', 'is_active', 'is_system'])]
class ChargeMethod extends Model
{
    use HasMasterRecord;

    protected function casts(): array
    {
        return [
            'applies_to' => ChargeAppliesTo::class,
            'is_system' => 'boolean',
        ];
    }

    public function masterCodesAreUppercase(): bool
    {
        return false;
    }

    public function deletionBlocker(): ?string
    {
        if ($this->is_system) {
            return 'This calculation method is part of the shop setup and cannot be removed.';
        }

        return null;
    }
}
