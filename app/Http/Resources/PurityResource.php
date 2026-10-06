<?php

namespace App\Http\Resources;

use App\Models\Purity;
use App\Services\Masters\Fineness;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Purity */
class PurityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'fineness' => $this->fineness,
            'fineness_percent' => Fineness::percentFromRatio((string) $this->fineness),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
