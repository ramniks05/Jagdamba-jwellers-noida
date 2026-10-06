<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Company */
class CompanyResource extends JsonResource
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
            'legal_name' => $this->legal_name,
            'display_name' => $this->displayName(),
            'logo_url' => $this->logo_url,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'website' => $this->website,
            'gstin' => $this->gstin,
            'pan' => $this->pan,
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
            'currency_code' => $this->currency_code,
            'fy_start_month' => $this->fy_start_month,
            'plan_code' => $this->plan_code,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'subscription_ends_at' => $this->subscription_ends_at?->toIso8601String(),
        ];
    }
}
