<?php

namespace App\Models;

use App\Models\Concerns\HasMasterRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'code', 'name', 'sort_order', 'is_active'])]
class Society extends Model
{
    use HasMasterRecord;

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function deletionBlocker(): ?string
    {
        return $this->usageBlocker('society', [
            'customers' => ['society_id', 'customer'],
        ]);
    }
}
