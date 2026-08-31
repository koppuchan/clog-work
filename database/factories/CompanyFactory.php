<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_code' => fake()->unique()->numerify('######'),
            'name' => fake()->company(),
            'is_closed_on_holidays' => true,
            'payroll_closing_day' => 25,
            'paid_leave_half_day' => false,
            'paid_leave_hourly' => false,
        ];
    }
}
