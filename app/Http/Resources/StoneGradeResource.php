<?php

namespace App\Http\Resources;

use App\Models\StoneGrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StoneGrade */
class StoneGradeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'kind' => $this->kind->value,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
