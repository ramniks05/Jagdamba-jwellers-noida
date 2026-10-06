<?php

namespace App\Services\Commerce;

use App\Enums\KycStatus;
use App\Models\Company;
use App\Models\Supplier;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierService
{
    public function __construct(private readonly CompanyContext $context) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, array $attributes): Supplier
    {
        return DB::transaction(function () use ($company, $attributes) {
            $this->context->ensureId((int) $company->id);

            return Supplier::query()->create($this->fields($attributes) + [
                'company_id' => $company->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Supplier $supplier, array $attributes): Supplier
    {
        return DB::transaction(function () use ($supplier, $attributes) {
            $this->context->ensureId((int) $supplier->company_id);
            $supplier->fill($this->fields($attributes));
            $supplier->save();

            return $supplier->refresh();
        });
    }

    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
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
            'contact_name' => $this->blank($attributes['contact_name'] ?? null),
            'mobile' => $this->blank($attributes['mobile'] ?? null),
            'email' => $this->blank($attributes['email'] ?? null),
            'address_line1' => $this->blank($attributes['address_line1'] ?? null),
            'address_line2' => $this->blank($attributes['address_line2'] ?? null),
            'city' => $this->blank($attributes['city'] ?? null),
            'state' => $this->blank($attributes['state'] ?? null),
            'postal_code' => $this->blank($attributes['postal_code'] ?? null),
            'country' => $this->blank($attributes['country'] ?? null) ?: 'India',
            'pan' => $this->upper($attributes['pan'] ?? null),
            'gstin' => $this->upper($attributes['gstin'] ?? null),
            'bank_name' => $this->blank($attributes['bank_name'] ?? null),
            'account_number' => $this->blank($attributes['account_number'] ?? null),
            'ifsc' => $this->upper($attributes['ifsc'] ?? null),
            'kyc_status' => KycStatus::from($attributes['kyc_status'] ?? KycStatus::Pending->value),
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
