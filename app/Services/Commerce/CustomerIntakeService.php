<?php

namespace App\Services\Commerce;

use App\Enums\CustomerType;
use App\Enums\IntakePurpose;
use App\Enums\IntakeStatus;
use App\Enums\KycStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerIntake;
use App\Support\CompanyContext;
use App\Support\IdentityRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerIntakeService
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly CustomerService $customers,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{result: string, customer: ?Customer, intake: ?CustomerIntake}
     */
    public function receive(Company $company, array $attributes): array
    {
        return DB::transaction(function () use ($company, $attributes) {
            $this->context->ensureId((int) $company->id);
            $mobile = trim((string) $attributes['mobile']);
            $key = $this->mobileKey($mobile);
            $known = $this->knownCustomer($key);
            $intent = $attributes['intent'] ?? 'new';

            if (in_array($intent, ['known', 'confirm', 'update'], true) && ! $known) {
                throw ValidationException::withMessages([
                    'mobile' => 'This mobile is not in the shop yet. Fill the new customer form.',
                ]);
            }

            if ($intent === 'known' || ($intent === 'new' && $known)) {
                return ['result' => 'known', 'customer' => $known, 'intake' => null];
            }

            if ($intent === 'confirm') {
                return ['result' => 'confirmed', 'customer' => null, 'intake' => null];
            }

            $waiting = CustomerIntake::query()
                ->where('status', IntakeStatus::Pending)
                ->where('mobile_key', $key)
                ->first();

            if ($intent === 'update') {
                $details = $this->details($company, $attributes, $mobile, $key) + [
                    'customer_id' => $known->id,
                    'purpose' => IntakePurpose::Update,
                ];

                if ($waiting) {
                    $waiting->fill($details);
                    $waiting->save();

                    return ['result' => 'updated', 'customer' => null, 'intake' => $waiting];
                }

                return [
                    'result' => 'updated',
                    'customer' => null,
                    'intake' => CustomerIntake::query()->create($details),
                ];
            }

            if ($waiting) {
                return ['result' => 'waiting', 'customer' => null, 'intake' => $waiting];
            }

            $intake = CustomerIntake::query()->create($this->details($company, $attributes, $mobile, $key) + [
                'purpose' => IntakePurpose::Create,
            ]);

            return ['result' => 'submitted', 'customer' => null, 'intake' => $intake];
        });
    }

    public function approve(CustomerIntake $intake, ?int $userId = null): Customer
    {
        return DB::transaction(function () use ($intake, $userId) {
            $this->context->ensureId((int) $intake->company_id);
            $locked = CustomerIntake::query()->whereKey($intake->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== IntakeStatus::Pending) {
                throw ValidationException::withMessages([
                    'intake' => 'This form has already been reviewed.',
                ]);
            }

            $customer = $locked->purpose === IntakePurpose::Update
                ? $this->applyUpdate($locked)
                : null;

            $customer ??= $this->knownCustomer($locked->mobile_key) ?: $this->customers->create($locked->company, [
                'name' => $locked->name,
                'mobile' => $locked->mobile,
                'email' => $locked->email,
                'address_line1' => $locked->address_line1,
                'city' => $locked->city,
                'state' => $locked->state,
                'postal_code' => $locked->postal_code,
                'country' => $locked->country,
                'pan' => $locked->pan,
                'gstin' => $locked->gstin,
                'dob' => $locked->dob?->toDateString(),
                'anniversary' => $locked->anniversary?->toDateString(),
                'kyc_status' => KycStatus::Pending->value,
                'customer_type' => CustomerType::Retail->value,
                'is_active' => true,
            ]);

            $locked->status = IntakeStatus::Approved;
            $locked->customer_id = $customer->id;
            $locked->reviewed_at = now();
            $locked->reviewed_by = $userId;
            $locked->save();

            return $customer;
        });
    }

    public function reject(CustomerIntake $intake, ?int $userId = null): void
    {
        DB::transaction(function () use ($intake, $userId) {
            $this->context->ensureId((int) $intake->company_id);
            $locked = CustomerIntake::query()->whereKey($intake->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== IntakeStatus::Pending) {
                throw ValidationException::withMessages([
                    'intake' => 'This form has already been reviewed.',
                ]);
            }

            $locked->status = IntakeStatus::Rejected;
            $locked->reviewed_at = now();
            $locked->reviewed_by = $userId;
            $locked->save();
        });
    }

    /**
     * @return array<string, ?string>
     */
    public function preview(Customer $customer): array
    {
        return [
            'name' => $customer->name,
            'mobile' => $customer->mobile,
            'email' => $customer->email,
            'address_line1' => $customer->address_line1,
            'city' => $customer->city,
            'state' => $customer->state,
            'postal_code' => $customer->postal_code,
            'pan' => $customer->pan,
            'gstin' => $customer->gstin,
            'dob' => $customer->dob?->toDateString(),
            'anniversary' => $customer->anniversary?->toDateString(),
        ];
    }

    public function mobileKey(string $mobile): string
    {
        return IdentityRules::mobileKey($mobile);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function details(Company $company, array $attributes, string $mobile, string $key): array
    {
        return [
            'company_id' => $company->id,
            'name' => $attributes['name'],
            'mobile' => $mobile,
            'mobile_key' => $key,
            'email' => $this->blank($attributes['email'] ?? null),
            'address_line1' => $attributes['address_line1'],
            'city' => $attributes['city'],
            'state' => $attributes['state'],
            'postal_code' => $attributes['postal_code'],
            'country' => $this->blank($attributes['country'] ?? null) ?: 'India',
            'pan' => $this->upper($attributes['pan'] ?? null),
            'gstin' => $this->upper($attributes['gstin'] ?? null),
            'dob' => $this->blank($attributes['dob'] ?? null),
            'anniversary' => $this->blank($attributes['anniversary'] ?? null),
            'status' => IntakeStatus::Pending,
        ];
    }

    private function applyUpdate(CustomerIntake $intake): ?Customer
    {
        $customer = $intake->customer_id
            ? Customer::query()->whereKey($intake->customer_id)->first()
            : null;
        $customer ??= $this->knownCustomer($intake->mobile_key);

        if (! $customer) {
            return null;
        }

        return $this->customers->update($customer, [
            'name' => $intake->name,
            'mobile' => $intake->mobile,
            'email' => $intake->email,
            'address_line1' => $intake->address_line1,
            'address_line2' => $customer->address_line2,
            'city' => $intake->city,
            'state' => $intake->state,
            'postal_code' => $intake->postal_code,
            'country' => $intake->country,
            'dob' => $intake->dob?->toDateString(),
            'anniversary' => $intake->anniversary?->toDateString(),
            'pan' => $intake->pan,
            'gstin' => $intake->gstin,
            'id_proof_type' => $customer->id_proof_type,
            'id_proof_number' => $customer->id_proof_number,
            'kyc_status' => $customer->kyc_status->value,
            'customer_type' => $customer->customer_type->value,
            'is_active' => $customer->is_active,
            'notes' => $customer->notes,
        ]);
    }

    private function knownCustomer(string $key): ?Customer
    {
        if ($key === '') {
            return null;
        }

        return Customer::query()
            ->where('is_system', false)
            ->where('mobile', 'like', '%'.substr($key, -10))
            ->orderByDesc('id')
            ->get()
            ->first(fn (Customer $customer) => $this->mobileKey((string) $customer->mobile) === $key);
    }

    private function blank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function upper(mixed $value): ?string
    {
        $value = $this->blank($value);

        return $value === null ? null : strtoupper($value);
    }
}
