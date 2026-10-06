<?php

namespace App\Models;

use App\Enums\StoneGradeKind;
use App\Models\Concerns\HasMasterRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'kind', 'code', 'name', 'sort_order', 'is_active'])]
class StoneGrade extends Model
{
    use HasMasterRecord;

    protected function casts(): array
    {
        return [
            'kind' => StoneGradeKind::class,
        ];
    }
}
