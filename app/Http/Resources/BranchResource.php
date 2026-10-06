<?php

namespace App\Http\Resources;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Branch */
class BranchResource extends JsonResource
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
            'is_head_office' => $this->is_head_office,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'gstin' => $this->gstin,
            'address' => [
                'line1' => $this->address_line1,
                'line2' => $this->address_line2,
                'city' => $this->city,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
                'country' => $this->country,
                'formatted' => $this->formattedAddress(),
            ],
            'status' => $this->status->value,
            'timezone' => $this->timezone,
        ];
    }
}
