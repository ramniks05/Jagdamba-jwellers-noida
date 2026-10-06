<?php

namespace App\Services\Commerce;

use App\Enums\DocumentType;
use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\GoldScheme;
use App\Models\Payment;
use App\Models\SchemeEnrollment;
use App\Models\SchemeInstallment;
use App\Services\Foundation\DocumentNumberService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SchemeService
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly SchemeBenefit $benefit,
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, array $attributes): GoldScheme
    {
        $this->context->ensureId((int) $company->id);
        $mode = $attributes['installment_mode'];
        $monthly = trim((string) ($attributes['monthly_amount'] ?? ''));

        if ($mode === 'fixed' && ($monthly === '' || BigDecimal::of($monthly)->isNegativeOrZero())) {
            throw ValidationException::withMessages([
                'monthly_amount' => 'A fixed scheme needs a monthly amount.',
            ]);
        }

        if ($attributes['bonus_type'] === 'extra_installment' && $mode !== 'fixed') {
            throw ValidationException::withMessages([
                'bonus_type' => 'An extra installment bonus needs a fixed monthly amount.',
            ]);
        }

        return GoldScheme::query()->create([
            'company_id' => $company->id,
            'code' => strtoupper((string) $attributes['code']),
            'name' => $attributes['name'],
            'installment_mode' => $mode,
            'monthly_amount' => $mode === 'fixed' ? $monthly : null,
            'duration_months' => (int) $attributes['duration_months'],
            'bonus_type' => $attributes['bonus_type'],
            'bonus_value' => $attributes['bonus_value'] ?? '0',
            'is_active' => filter_var($attributes['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function enroll(GoldScheme $scheme, Customer $customer): SchemeEnrollment
    {
        return DB::transaction(function () use ($scheme, $customer) {
            $this->context->ensureId((int) $scheme->company_id);

            if (! $scheme->is_active) {
                throw ValidationException::withMessages([
                    'scheme' => 'This scheme is not open for new members.',
                ]);
            }

            if ($customer->is_system) {
                throw ValidationException::withMessages([
                    'customer_uuid' => 'Walk-in customers cannot join a scheme.',
                ]);
            }

            $branch = Branch::query()->where('is_head_office', true)->first()
                ?? Branch::query()->orderBy('id')->first();
            $number = $this->numbers->issue($this->numbers->for(DocumentType::Scheme, $branch));
            $enrollment = SchemeEnrollment::query()->create([
                'company_id' => $scheme->company_id,
                'gold_scheme_id' => $scheme->id,
                'customer_id' => $customer->id,
                'number' => $number->number,
                'started_on' => now()->toDateString(),
                'status' => 'active',
            ]);
            $start = Carbon::parse($enrollment->started_on);

            for ($month = 0; $month < $scheme->duration_months; $month++) {
                SchemeInstallment::query()->create([
                    'company_id' => $scheme->company_id,
                    'scheme_enrollment_id' => $enrollment->id,
                    'due_on' => $start->copy()->addMonths($month)->toDateString(),
                    'amount' => $scheme->monthly_amount ?? '0.00',
                ]);
            }

            return $enrollment->load('installments');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function pay(SchemeEnrollment $enrollment, array $attributes, ?int $userId = null): SchemeInstallment
    {
        return DB::transaction(function () use ($enrollment, $attributes, $userId) {
            $this->context->ensureId((int) $enrollment->company_id);
            $enrollment->load('scheme');

            if ($enrollment->status !== 'active') {
                throw ValidationException::withMessages([
                    'amount' => 'This scheme is already closed.',
                ]);
            }

            $installment = $enrollment->installments()->whereNull('paid_at')->orderBy('due_on')->orderBy('id')->first();

            if (! $installment) {
                throw ValidationException::withMessages([
                    'amount' => 'Every installment is already paid.',
                ]);
            }

            $amount = BigDecimal::of((string) $attributes['amount'])->toScale(2, RoundingMode::HalfUp);

            if ($amount->isNegativeOrZero()) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter an amount more than zero.',
                ]);
            }

            if ($enrollment->scheme->installment_mode === 'fixed' && ! $amount->isEqualTo((string) $enrollment->scheme->monthly_amount)) {
                throw ValidationException::withMessages([
                    'amount' => 'This scheme collects '.$enrollment->scheme->monthly_amount.' each month.',
                ]);
            }

            $branch = Branch::query()->where('is_head_office', true)->first()
                ?? Branch::query()->orderBy('id')->first();
            $receipt = $this->numbers->issue($this->numbers->for(DocumentType::Receipt, $branch));
            $payment = Payment::query()->create([
                'company_id' => $enrollment->company_id,
                'branch_id' => $branch?->id,
                'customer_id' => $enrollment->customer_id,
                'number' => $receipt->number,
                'direction' => 'in',
                'method' => PaymentMethod::from($attributes['method']),
                'amount' => (string) $amount,
                'reference' => trim((string) ($attributes['reference'] ?? '')) ?: null,
                'narration' => 'Scheme '.$enrollment->number,
                'received_at' => now(),
                'user_id' => $userId,
            ]);
            $installment->payment_id = $payment->id;
            $installment->amount = (string) $amount;
            $installment->paid_at = now();
            $installment->save();

            return $installment;
        });
    }

    public function mature(SchemeEnrollment $enrollment, ?int $userId = null): SchemeEnrollment
    {
        return DB::transaction(function () use ($enrollment, $userId) {
            $this->context->ensureId((int) $enrollment->company_id);
            $enrollment->load(['scheme', 'installments']);

            if ($enrollment->status !== 'active') {
                throw ValidationException::withMessages([
                    'scheme' => 'This scheme is already closed.',
                ]);
            }

            $paid = $enrollment->installments->whereNotNull('paid_at');

            if ($paid->count() < $enrollment->scheme->duration_months) {
                throw ValidationException::withMessages([
                    'scheme' => 'Collect every installment before the scheme matures.',
                ]);
            }

            $paidAmount = $paid->reduce(
                fn (BigDecimal $sum, SchemeInstallment $row) => $sum->plus((string) $row->amount),
                BigDecimal::zero(),
            );
            $value = $this->benefit->maturity(
                (string) $paidAmount->toScale(2, RoundingMode::HalfUp),
                (string) ($enrollment->scheme->monthly_amount ?? '0'),
                $enrollment->scheme->bonus_type,
                (string) $enrollment->scheme->bonus_value,
            );
            $this->ledger->post(
                (int) $enrollment->company_id,
                PartyType::Customer,
                (int) $enrollment->customer_id,
                LedgerDirection::Credit,
                $value,
                'Scheme maturity '.$enrollment->number,
                $enrollment,
                $userId,
            );
            $enrollment->status = 'matured';
            $enrollment->matured_at = now();
            $enrollment->maturity_amount = $value;
            $enrollment->save();

            return $enrollment;
        });
    }
}
