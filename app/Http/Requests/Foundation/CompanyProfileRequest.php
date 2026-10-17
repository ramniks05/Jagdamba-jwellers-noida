<?php

namespace App\Http\Requests\Foundation;

use App\Support\IdentityRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyProfileRequest extends FormRequest
{
    use NormalizesShopIdentity;

    public function authorize(): bool
    {
        $company = $this->user()?->company;

        return $company !== null && (bool) $this->user()?->can('update', $company);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name' => ['required', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'code' => ['required', 'regex:'.IdentityRules::CODE, Rule::unique('companies', 'code')->ignore($companyId)],
            'website' => ['nullable', 'url', 'max:200'],
            'facebook' => ['nullable', 'url', 'max:200'],
            'instagram' => ['nullable', 'url', 'max:200'],
            'youtube' => ['nullable', 'url', 'max:200'],
            'pan' => ['nullable', 'regex:'.IdentityRules::PAN, Rule::unique('companies', 'pan')->ignore($companyId)],
            'timezone' => ['required', 'timezone'],
            'currency_code' => ['required', 'regex:/^[A-Z]{3}$/'],
            'fy_start_month' => ['required', 'integer', 'between:1,12'],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'signature' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'remove_signature' => ['sometimes', 'boolean'],
            ...$this->contactRules(),
            'gstin' => ['nullable', 'regex:'.IdentityRules::GSTIN, Rule::unique('companies', 'gstin')->ignore($companyId)],
            ...$this->addressRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->identityMessages(),
            'signature.uploaded' => 'The signature did not upload. Choose a JPEG or PNG photo under 2 MB and save the profile again.',
            'signature.image' => 'The signature must be a JPEG, PNG, or WebP photo.',
            'signature.mimes' => 'The signature must be a JPEG, PNG, or WebP photo.',
            'signature.max' => 'The signature is larger than 2 MB, so it was not saved. Choose a smaller photo and save the profile again.',
            'logo.uploaded' => 'The logo did not upload. Choose a JPEG or PNG photo under 2 MB and save the profile again.',
            'logo.image' => 'The logo must be a JPEG, PNG, or WebP photo.',
            'logo.mimes' => 'The logo must be a JPEG, PNG, or WebP photo.',
            'logo.max' => 'The logo is larger than 2 MB, so it was not saved. Choose a smaller photo and save the profile again.',
        ];
    }
}
