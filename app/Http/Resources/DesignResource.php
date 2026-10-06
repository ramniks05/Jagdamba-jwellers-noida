<?php

namespace App\Http\Resources;

use App\Models\Design;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Design */
class DesignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'design_number' => $this->design_number,
            'name' => $this->name,
            'description' => $this->description,
            'collection_uuid' => $this->collection?->uuid,
            'category_uuid' => $this->category?->uuid,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
