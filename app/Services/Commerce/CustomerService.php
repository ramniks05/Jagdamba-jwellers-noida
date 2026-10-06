<?php

namespace App\Services\Commerce;

use App\Enums\CustomerType;
use App\Enums\KycStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(private readonly CompanyContext $context) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, array $attributes): Customer
    {
        return DB::transaction(function () use ($company, $attributes) {
            $this->context->ensureId((int) $company->id);

            return Customer::query()->create($this->fields($attributes) + [
                'company_id' => $company->id,
                'is_system' => false,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Customer $customer, array $attributes): Customer
    {
        return DB::transaction(function () use ($customer, $attributes) {
            $this->context->ensureId((int) $customer->company_id);
            $fields = $this->fields($attributes);

            if ($customer->is_system) {
                $fields['code'] = $customer->code;
                $fields['is_active'] = true;
            }

            $customer->fill($fields);
            $customer->save();

            return $customer->refresh();
        });
    }

    public function delete(Customer $customer): void
    {
        if ($customer->is_system || $customer->sales()->exists()) {
            throw ValidationException::withMessages([
                'record' => 'This customer has bills and stays on the ledger.',
            ]);
        }

        $customer->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function fields(array $attributes): array
    {
        return [
            'code' => Str::upper(trim((string) $attributes['code'])),
            'name' => $attributes['name'],
            'mobile' => $this->blank($attributes['mobile'] ?? null),
            'email' => $this->blank($attributes['email'] ?? null),
            'address_line1' => $this->blank($attributes['address_line1'] ?? null),
            'address_line2' => $this->blank($attributes['address_line2'] ?? null),
            'city' => $this->blank($attributes['city'] ?? null),
            'state' => $this->blank($attributes['state'] ?? null),
            'postal_code' => $this->blank($attributes['postal_code'] ?? null),
            'country' => $this->blank($attributes['country'] ?? null) ?: 'India',
            'dob' => $this->blank($attributes['dob'] ?? null),
            'anniversary' => $this->blank($attributes['anniversary'] ?? null),
            'pan' => $this->upper($attributes['pan'] ?? null),
            'gstin' => $this->upper($attributes['gstin'] ?? null),
            'id_proof_type' => $this->blank($attributes['id_proof_type'] ?? null),
            'id_proof_number' => $this->blank($attributes['id_proof_number'] ?? null),
            'kyc_status' => KycStatus::from($attributes['kyc_status'] ?? KycStatus::Pending->value),
            'customer_type' => CustomerType::from($attributes['customer_type'] ?? CustomerType::Retail->value),
            'is_active' => filter_var($attributes['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'notes' => $this->blank($attributes['notes'] ?? null),
        ];
    }

    private function blank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function upper(mixed $value): ?string
    {
        $value = $this->blank($value);

        return $value === null ? null : Str::upper($value);
    }
}
