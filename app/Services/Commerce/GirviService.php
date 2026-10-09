<?php

namespace App\Services\Commerce;

use App\Enums\DocumentType;
use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\GirviPledge;
use App\Models\GirviPledgeItem;
use App\Models\MetalType;
use App\Models\Payment;
use App\Models\Purity;
use App\Services\Foundation\DocumentNumberService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GirviService
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly GirviPricer $pricer,
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function pledge(Company $company, array $attributes, ?int $userId = null): GirviPledge
    {
        return DB::transaction(function () use ($company, $attributes, $userId) {
            $this->context->ensureId((int) $company->id);
            $customer = Customer::query()->where('uuid', $attributes['customer_uuid'])->where('is_active', true)->first();

            if (! $customer || $customer->is_system) {
                throw ValidationException::withMessages([
                    'customer_uuid' => 'Choose the customer. Girvi cannot be in the walk-in name.',
                ]);
            }

            $mode = $attributes['loan_mode'] === 'amount' ? 'amount' : 'percent';
            $lines = $this->lines($attributes['pieces'] ?? []);
            $goldValue = $this->sum($lines, 'gold_value');
            $principal = $this->pricer->principal(
                $goldValue,
                $mode,
                (string) ($attributes['loan_percent'] ?? '0'),
                (string) ($attributes['loan_amount'] ?? '0'),
            );
            $netWeight = $this->sum($lines, 'net_weight', 3);
            $first = $lines[0];
            $branch = Branch::query()->where('is_head_office', true)->first()
                ?? Branch::query()->orderBy('id')->first();
            $number = $this->numbers->issue($this->numbers->for(DocumentType::Girvi, $branch));
            $pledge = GirviPledge::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branch?->id,
                'customer_id' => $customer->id,
                'metal_type_id' => $first['metal_type_id'],
                'purity_id' => $first['purity_id'],
                'number' => $number->number,
                'description' => $this->summary($lines),
                'gross_weight' => $this->sum($lines, 'gross_weight', 3),
                'stone_weight' => $this->sum($lines, 'stone_weight', 3),
                'net_weight' => $netWeight,
                'rate_per_gram' => $this->averageRate($goldValue, $netWeight),
                'gold_value' => $goldValue,
                'loan_mode' => $mode,
                'loan_percent' => $mode === 'percent' ? $attributes['loan_percent'] : null,
                'principal' => $principal,
                'interest_percent' => $attributes['interest_percent'],
                'interest_charged' => '0',
                'status' => 'open',
                'pledged_at' => now(),
                'interest_from' => now()->toDateString(),
                'notes' => trim((string) ($attributes['notes'] ?? '')) ?: null,
                'user_id' => $userId,
            ]);

            foreach ($lines as $line) {
                GirviPledgeItem::query()->create([
                    'company_id' => $company->id,
                    'girvi_pledge_id' => $pledge->id,
                    'metal_type_id' => $line['metal_type_id'],
                    'purity_id' => $line['purity_id'],
                    'description' => $line['description'],
                    'gross_weight' => $line['gross_weight'],
                    'stone_weight' => $line['stone_weight'],
                    'net_weight' => $line['net_weight'],
                    'rate_per_gram' => $line['rate_per_gram'],
                    'gold_value' => $line['gold_value'],
                ]);
            }

            $voucher = $this->numbers->issue($this->numbers->for(DocumentType::Payment, $branch));
            Payment::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branch?->id,
                'girvi_pledge_id' => $pledge->id,
                'customer_id' => $customer->id,
                'number' => $voucher->number,
                'direction' => 'out',
                'method' => PaymentMethod::Cash,
                'amount' => $principal,
                'narration' => 'Girvi '.$pledge->number,
                'received_at' => now(),
                'user_id' => $userId,
            ]);
            $this->ledger->post(
                (int) $company->id,
                PartyType::Customer,
                (int) $customer->id,
                LedgerDirection::Debit,
                $principal,
                'Girvi '.$pledge->number,
                $pledge,
                $userId,
            );

            return $pledge;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function settle(GirviPledge $pledge, array $attributes, ?int $userId = null): GirviPledge
    {
        return DB::transaction(function () use ($pledge, $attributes, $userId) {
            $this->context->ensureId((int) $pledge->company_id);

            if ($pledge->status !== 'open') {
                throw ValidationException::withMessages([
                    'payment' => 'This girvi is already closed.',
                ]);
            }

            $months = (int) $attributes['months'];
            $owed = $this->pricer->monthsBetween($pledge->interest_from, now());

            if ($months < $owed) {
                throw ValidationException::withMessages([
                    'months' => 'Interest is due for '.$owed.' month'.($owed === 1 ? '' : 's').' since '.$pledge->interest_from->format('d-m-Y').'.',
                ]);
            }

            $interest = $this->pricer->interest((string) $pledge->principal, (string) $pledge->interest_percent, $months);
            $release = $attributes['action'] === 'release';
            $due = $release
                ? (string) BigDecimal::of((string) $pledge->principal)->plus($interest)->toScale(2, RoundingMode::HalfUp)
                : $interest;
            $paid = BigDecimal::of((string) $attributes['payment'])->toScale(2, RoundingMode::HalfUp);

            if (! $release && BigDecimal::of($interest)->isZero()) {
                throw ValidationException::withMessages([
                    'payment' => 'There is no interest to collect for zero months.',
                ]);
            }

            if (! $paid->isEqualTo($due)) {
                throw ValidationException::withMessages([
                    'payment' => $release
                        ? 'To release the gold, collect the loan plus interest, '.$due.'.'
                        : 'The interest for '.$months.' month'.($months === 1 ? '' : 's').' is '.$due.'.',
                ]);
            }

            $branch = $pledge->branch;
            $voucher = $this->numbers->issue($this->numbers->for(DocumentType::Receipt, $branch));
            $payment = Payment::query()->create([
                'company_id' => $pledge->company_id,
                'branch_id' => $pledge->branch_id,
                'girvi_pledge_id' => $pledge->id,
                'customer_id' => $pledge->customer_id,
                'number' => $voucher->number,
                'direction' => 'in',
                'method' => PaymentMethod::from($attributes['method']),
                'amount' => (string) $paid,
                'reference' => trim((string) ($attributes['reference'] ?? '')) ?: null,
                'narration' => ($release ? 'Girvi release ' : 'Girvi interest ').$pledge->number,
                'received_at' => now(),
                'user_id' => $userId,
            ]);

            if (BigDecimal::of($interest)->isPositive()) {
                $this->ledger->post(
                    (int) $pledge->company_id,
                    PartyType::Customer,
                    (int) $pledge->customer_id,
                    LedgerDirection::Debit,
                    $interest,
                    'Girvi interest '.$pledge->number,
                    $pledge,
                    $userId,
                );
            }

            $this->ledger->post(
                (int) $pledge->company_id,
                PartyType::Customer,
                (int) $pledge->customer_id,
                LedgerDirection::Credit,
                (string) $paid,
                'Receipt '.$payment->number,
                $payment,
                $userId,
            );
            $pledge->interest_charged = (string) BigDecimal::of((string) $pledge->interest_charged)->plus($interest)->toScale(2, RoundingMode::HalfUp);

            if ($release) {
                $pledge->status = 'released';
                $pledge->released_at = now();
            } else {
                $pledge->interest_from = now()->toDateString();
            }

            $pledge->save();

            return $pledge;
        });
    }

    /**
     * @param  array<int, mixed>  $pieces
     * @return array<int, array<string, mixed>>
     */
    private function lines(array $pieces): array
    {
        if ($pieces === []) {
            throw ValidationException::withMessages([
                'pieces' => 'Add at least one piece.',
            ]);
        }

        $lines = [];

        foreach (array_values($pieces) as $index => $piece) {
            if (! is_array($piece)) {
                continue;
            }

            $metal = MetalType::query()->where('uuid', $piece['metal_uuid'] ?? '')->first();
            $purity = Purity::query()->where('uuid', $piece['purity_uuid'] ?? '')->first();

            if (! $metal || ! $purity || (int) $purity->metal_type_id !== (int) $metal->id) {
                throw ValidationException::withMessages([
                    'pieces.'.$index.'.purity_uuid' => 'Choose a purity that belongs to this metal.',
                ]);
            }

            try {
                $valued = $this->pricer->piece(
                    (string) ($piece['gross_weight'] ?? '0'),
                    (string) ($piece['stone_weight'] ?? '0'),
                    (string) ($piece['rate_per_gram'] ?? '0'),
                );
            } catch (ValidationException $exception) {
                $errors = [];

                foreach ($exception->errors() as $field => $messages) {
                    $errors['pieces.'.$index.'.'.$field] = $messages;
                }

                throw ValidationException::withMessages($errors);
            }

            $lines[] = [
                'metal_type_id' => $metal->id,
                'purity_id' => $purity->id,
                'description' => trim((string) ($piece['description'] ?? '')),
                'gross_weight' => $piece['gross_weight'],
                'stone_weight' => $piece['stone_weight'] ?? '0',
                'net_weight' => $valued['net_weight'],
                'rate_per_gram' => $piece['rate_per_gram'],
                'gold_value' => $valued['gold_value'],
            ];
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'pieces' => 'Add at least one piece.',
            ]);
        }

        return $lines;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function sum(array $lines, string $key, int $scale = 2): string
    {
        $total = BigDecimal::zero();

        foreach ($lines as $line) {
            $total = $total->plus((string) $line[$key]);
        }

        return (string) $total->toScale($scale, RoundingMode::HalfUp);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function summary(array $lines): string
    {
        $names = collect($lines)->pluck('description')->filter()->unique()->implode(', ');

        return mb_strlen($names) > 160 ? mb_substr($names, 0, 157).'...' : $names;
    }

    private function averageRate(string $goldValue, string $netWeight): string
    {
        $net = BigDecimal::of($netWeight);

        if ($net->isZero()) {
            return '0.00';
        }

        return (string) BigDecimal::of($goldValue)->dividedBy($net, 2, RoundingMode::HalfUp);
    }
}
