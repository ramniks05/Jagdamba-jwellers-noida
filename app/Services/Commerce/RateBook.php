<?php

namespace App\Services\Commerce;

use App\Models\Branch;
use App\Models\Company;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\Purity;
use App\Support\CompanyContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RateBook
{
    public function __construct(private readonly CompanyContext $context) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(Company $company, array $attributes, ?int $userId = null): MetalRate
    {
        return DB::transaction(function () use ($company, $attributes, $userId) {
            $this->context->ensureId((int) $company->id);
            $metal = MetalType::query()->where('uuid', $attributes['metal_uuid'])->first();
            $purity = Purity::query()->where('uuid', $attributes['purity_uuid'])->first();

            if (! $metal || ! $purity || (int) $purity->metal_type_id !== (int) $metal->id) {
                throw ValidationException::withMessages([
                    'purity_uuid' => 'Choose a purity for the selected metal.',
                ]);
            }

            $branchId = null;

            if (! empty($attributes['branch_uuid'])) {
                $branch = Branch::query()->where('uuid', $attributes['branch_uuid'])->first();

                if (! $branch) {
                    throw ValidationException::withMessages([
                        'branch_uuid' => 'Choose a branch from this shop.',
                    ]);
                }

                $branchId = $branch->id;
            }

            return MetalRate::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branchId,
                'metal_type_id' => $metal->id,
                'purity_id' => $purity->id,
                'rate_per_gram' => $attributes['rate_per_gram'],
                'effective_at' => Carbon::parse($attributes['effective_at'] ?? now()),
                'source' => $attributes['source'] ?? 'manual',
                'user_id' => $userId,
                'note' => $attributes['note'] ?? null,
            ]);
        });
    }

    public function current(int $metalTypeId, int $purityId, ?int $branchId, ?CarbonInterface $at = null): ?MetalRate
    {
        $at = $at ?? now();

        if ($branchId) {
            $branchRate = $this->latest($metalTypeId, $purityId, $branchId, $at);

            if ($branchRate) {
                return $branchRate;
            }
        }

        return $this->latest($metalTypeId, $purityId, null, $at);
    }

    private function latest(int $metalTypeId, int $purityId, ?int $branchId, CarbonInterface $at): ?MetalRate
    {
        return MetalRate::query()
            ->where('metal_type_id', $metalTypeId)
            ->where('purity_id', $purityId)
            ->where('effective_at', '<=', $at)
            ->when(
                $branchId,
                fn ($query) => $query->where('branch_id', $branchId),
                fn ($query) => $query->whereNull('branch_id'),
            )
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();
    }
}
