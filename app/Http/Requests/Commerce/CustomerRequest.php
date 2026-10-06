<?php

namespace App\Http\Requests\Commerce;

use App\Enums\CustomerType;
use App\Enums\KycStatus;
use App\Models\Customer;
use App\Support\IdentityRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        if ($customer instanceof Customer) {
            return (bool) $this->user()?->can('update', $customer);
        }

        return (bool) $this->user()?->can('create', Customer::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper(trim((string) $this->input('code'))),
            'pan' => Str::upper(trim((string) $this->input('pan'))),
            'gstin' => Str::upper(trim((string) $this->input('gstin'))),
        ]);

        foreach (['mobile', 'email', 'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'dob', 'anniversary', 'pan', 'gstin', 'id_proof_type', 'id_proof_number', 'notes'] as $field) {
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
        $customer = $this->route('customer');
        $ignore = $customer instanceof Customer ? $customer->id : null;

        return [
            'code' => ['required', 'regex:'.IdentityRules::CODE, Rule::unique('customers', 'code')->where(fn ($query) => $query->where('company_id', $this->user()->company_id))->ignore($ignore)],
            'name' => ['required', 'string', 'max:160'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:160'],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'country' => ['nullable', 'string', 'max:100'],
            'dob' => ['nullable', 'date'],
            'anniversary' => ['nullable', 'date'],
            'pan' => ['nullable', 'regex:'.IdentityRules::PAN],
            'gstin' => ['nullable', 'regex:'.IdentityRules::GSTIN],
            'id_proof_type' => ['nullable', 'string', 'max:40'],
            'id_proof_number' => ['nullable', 'string', 'max:40'],
            'kyc_status' => ['required', Rule::enum(KycStatus::class)],
            'customer_type' => ['required', Rule::enum(CustomerType::class)],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
