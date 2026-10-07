<?php

namespace App\Services\Commerce;

use App\Enums\CustomerType;
use App\Enums\IntakeStatus;
use App\Enums\KycStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerIntake;
use App\Support\CompanyContext;
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

            if ($known) {
                return ['result' => 'known', 'customer' => $known, 'intake' => null];
            }

            if (($attributes['intent'] ?? 'new') === 'known') {
                throw ValidationException::withMessages([
                    'mobile' => 'This mobile is not in the shop yet. Fill the new customer form.',
                ]);
            }

            $waiting = CustomerIntake::query()
                ->where('status', IntakeStatus::Pending)
                ->where('mobile_key', $key)
                ->first();

            if ($waiting) {
                return ['result' => 'waiting', 'customer' => null, 'intake' => $waiting];
            }

            $intake = CustomerIntake::query()->create([
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
                'status' => IntakeStatus::Pending,
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

            $known = $this->knownCustomer($locked->mobile_key);
            $customer = $known ?: $this->customers->create($locked->company, [
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

    public function mobileKey(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if (str_starts_with($digits, '91') && strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return $digits;
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
