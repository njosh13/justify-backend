<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use App\Models\Firm;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
final class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'firm_id' => Firm::factory(),
            'full_name' => fake()->name(),
            'client_type' => 'individual',
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'is_withholding_agent' => false,
            'is_vat_exempt' => false,
        ];
    }

    public function withholdingAgent(): static
    {
        return $this->state([
            'client_type' => 'company',
            'full_name' => fake()->company().' Ltd',
            'kra_pin' => 'P'.fake()->numerify('#########').'Y',
            'is_withholding_agent' => true,
        ]);
    }

    public function vatExempt(string $reference = 'KRA/EXM/0001'): static
    {
        return $this->state(['is_vat_exempt' => true, 'vat_exemption_reference' => $reference]);
    }
}
