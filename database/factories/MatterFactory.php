<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CourtLevel;
use App\Models\Client;
use App\Models\Firm;
use App\Models\Matter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Matter> */
final class MatterFactory extends Factory
{
    protected $model = Matter::class;

    public function definition(): array
    {
        return [
            'firm_id' => Firm::factory(),
            'client_id' => fn (array $attributes) => Client::factory()->create(['firm_id' => $attributes['firm_id']])->id,
            'title' => 'Sale of LR No. '.fake()->numerify('####/##'),
            'reference' => 'M/'.fake()->numerify('####'),
            'court_level' => CourtLevel::None,
            'status' => 'open',
            'opened_on' => now()->toDateString(),
        ];
    }

    public function highCourt(): static
    {
        return $this->state([
            'title' => 'HCCC '.fake()->numerify('###').' of 2026',
            'court_level' => CourtLevel::HighCourt,
            'cause_number' => 'HCCC '.fake()->numerify('###').'/2026',
        ]);
    }
}
