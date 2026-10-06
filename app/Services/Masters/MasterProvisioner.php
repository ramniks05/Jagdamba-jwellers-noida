<?php

namespace App\Services\Masters;

use App\Enums\ChargeAppliesTo;
use App\Enums\StoneGradeKind;
use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\Company;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\StoneGrade;
use App\Models\StoneType;
use App\Support\CompanyContext;

class MasterProvisioner
{
    public function __construct(private readonly CompanyContext $context) {}

    public function seed(Company $company): void
    {
        $this->context->ensureId((int) $company->id);

        $this->seedNamed(Category::class, config('masters.categories'));
        $this->seedNamed(MetalType::class, config('masters.metals'));
        $this->seedPurities($company);
        $this->seedNamed(StoneType::class, config('masters.stone_types'));
        $this->seedGrades();
        $this->seedCharges();
    }

    /**
     * @param  class-string<Category|MetalType|StoneType>  $class
     * @param  array<int, array{code: string, name: string}>  $rows
     */
    private function seedNamed(string $class, array $rows): void
    {
        foreach ($rows as $index => $row) {
            $class::query()->firstOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedPurities(Company $company): void
    {
        $metals = MetalType::query()->pluck('id', 'code');

        foreach (config('masters.purities') as $index => $row) {
            $metalId = $metals[$row['metal']] ?? null;

            if (! $metalId) {
                continue;
            }

            Purity::query()->firstOrCreate(
                [
                    'metal_type_id' => $metalId,
                    'code' => $row['code'],
                ],
                [
                    'company_id' => $company->id,
                    'name' => $row['name'],
                    'fineness' => $row['fineness'],
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedGrades(): void
    {
        foreach (config('masters.stone_grades') as $index => $row) {
            StoneGrade::query()->firstOrCreate(
                [
                    'kind' => StoneGradeKind::from($row['kind']),
                    'code' => $row['code'],
                ],
                [
                    'name' => $row['name'],
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedCharges(): void
    {
        foreach (config('masters.charge_methods') as $index => $row) {
            ChargeMethod::query()->firstOrCreate(
                [
                    'applies_to' => ChargeAppliesTo::from($row['applies_to']),
                    'code' => $row['code'],
                ],
                [
                    'name' => $row['name'],
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                    'is_system' => true,
                ],
            );
        }
    }
}
