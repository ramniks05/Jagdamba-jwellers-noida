<?php

namespace App\Models;

use App\Models\Concerns\HasMasterRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'collection_id', 'category_id', 'design_number', 'name', 'description', 'sort_order', 'is_active'])]
class Design extends Model
{
    use HasMasterRecord;

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
