<?php

namespace App\Http\Resources;

use App\Models\FinancialYear;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FinancialYear */
class FinancialYearResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_current' => $this->is_current,
            'is_closed' => $this->is_closed,
            'closed_at' => $this->closed_at?->toIso8601String(),
        ];
    }
}
