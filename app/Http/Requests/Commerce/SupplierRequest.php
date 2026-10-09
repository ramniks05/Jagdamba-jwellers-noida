<?php

namespace App\Http\Requests\Commerce;

use App\Enums\KycStatus;
use App\Models\Supplier;
use App\Support\IdentityRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');

        if ($supplier instanceof Supplier) {
            return (bool) $this->user()?->can('update', $supplier);
        }

        return (bool) $this->user()?->can('create', Supplier::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper(trim((string) $this->input('code'))),
            'mobile' => preg_replace('/[\s-]+/', '', trim((string) $this->input('mobile'))),
            'pan' => Str::upper(trim((string) $this->input('pan'))),
            'gstin' => Str::upper(trim((string) $this->input('gstin'))),
            'ifsc' => Str::upper(trim((string) $this->input('ifsc'))),
        ]);

        foreach (['contact_name', 'mobile', 'email', 'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'pan', 'gstin', 'bank_name', 'account_number', 'ifsc', 'notes'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');
        $ignore = $supplier instanceof Supplier ? $supplier->id : null;

        return [
            'code' => ['required', 'regex:'.IdentityRules::CODE, Rule::unique('suppliers', 'code')->where(fn ($query) => $query->where('company_id', $this->user()->company_id))->ignore($ignore)],
            'name' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'mobile' => ['nullable', 'string', 'max:20', 'regex:'.IdentityRules::MOBILE],
            'email' => ['nullable', 'email', 'max:160'],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'country' => ['nullable', 'string', 'max:100'],
            'pan' => ['nullable', 'regex:'.IdentityRules::PAN],
            'gstin' => ['nullable', 'regex:'.IdentityRules::GSTIN],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:40'],
            'ifsc' => ['nullable', 'regex:'.IdentityRules::IFSC],
            'kyc_status' => ['required', Rule::enum(KycStatus::class)],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return IdentityRules::messages() + [
            'code.unique' => 'Another supplier already uses this code.',
        ];
    }
}
