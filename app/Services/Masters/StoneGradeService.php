<?php

namespace App\Services\Masters;

use App\Enums\StoneGradeKind;
use App\Models\Company;
use App\Models\StoneGrade;

class StoneGradeService
{
    public function __construct(private readonly MasterRecordService $records) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, array $attributes): StoneGrade
    {
        /** @var StoneGrade $grade */
        $grade = $this->records->create(StoneGrade::class, $company, $attributes, function (StoneGrade $grade, array $attributes) {
            $grade->kind = StoneGradeKind::from($attributes['kind']);
        });

        return $grade;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(StoneGrade $grade, array $attributes): StoneGrade
    {
        /** @var StoneGrade $grade */
        $grade = $this->records->update($grade, $attributes, function (StoneGrade $grade, array $attributes) {
            $grade->kind = StoneGradeKind::from($attributes['kind']);
        });

        return $grade;
    }

    public function delete(StoneGrade $grade): void
    {
        $this->records->delete($grade);
    }
}
