<?php

namespace Database\Factories;

use App\Enums\BranchStatus;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper(fake()->unique()->bothify('BR##')),
            'name' => fake()->streetName().' Branch',
            'is_head_office' => false,
            'status' => BranchStatus::Active,
            'country' => 'India',
            'address_line1' => fake()->streetAddress(),
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400001',
        ];
    }
}
