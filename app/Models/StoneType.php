<?php

namespace App\Models;

use App\Models\Concerns\HasMasterRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'code', 'name', 'sort_order', 'is_active'])]
class StoneType extends Model
{
    use HasMasterRecord;
}
