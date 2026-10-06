<?php

namespace App\Http\Resources;

use App\Models\ChargeMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChargeMethod */
class ChargeMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'applies_to' => $this->applies_to->value,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'is_system' => $this->is_system,
            'sort_order' => $this->sort_order,
        ];
    }
}
