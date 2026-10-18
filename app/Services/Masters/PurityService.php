<?php

namespace App\Services\Masters;

use App\Models\Company;
use App\Models\MetalType;
use App\Models\Purity;

class PurityService
{
    public function __construct(private readonly MasterRecordService $records) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, MetalType $metal, array $attributes): Purity
    {
        /** @var Purity $purity */
        $purity = $this->records->create(Purity::class, $company, $attributes, function (Purity $purity, array $attributes) use ($metal) {
            $purity->metal_type_id = $metal->id;
            $purity->fineness = self::ratio($attributes);
        });

        return $purity;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Purity $purity, array $attributes): Purity
    {
        /** @var Purity $purity */
        $purity = $this->records->update($purity, $attributes, function (Purity $purity, array $attributes) {
            $purity->fineness = self::ratio($attributes);
        });

        return $purity;
    }

    public function delete(Purity $purity): void
    {
        $this->records->delete($purity);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function ratio(array $attributes): ?string
    {
        $percent = trim((string) ($attributes['fineness_percent'] ?? ''));

        return $percent === '' ? null : Fineness::ratioFromPercent($percent);
    }
}
