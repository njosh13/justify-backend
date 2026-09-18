<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Firm;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Firm> */
final class FirmFactory extends Factory
{
    protected $model = Firm::class;

    public function definition(): array
    {
        return [
            'name' => fake()->lastName().' & Co. Advocates',
            'plan' => 'solo',
            'kra_pin' => 'P'.fake()->numerify('#########').'X',
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'vat_registered' => true,
            'rounding_policy' => 'shilling_half_up',
            'default_cost_basis' => 'advocate_client',
            'bill_number_prefix' => 'FN',
        ];
    }

    public function notVatRegistered(): static
    {
        return $this->state(['vat_registered' => false]);
    }
}
