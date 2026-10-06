<?php

namespace Database\Factories;

use App\Enums\CompanyStatus;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SH##??')),
            'name' => fake()->company(),
            'country' => 'India',
            'status' => CompanyStatus::Active,
            'timezone' => 'Asia/Kolkata',
            'currency_code' => 'INR',
            'fy_start_month' => 4,
            'address_line1' => fake()->streetAddress(),
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400001',
        ];
    }
}
