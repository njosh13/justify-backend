<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Aro\Models\AroVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AroVersion> */
final class AroVersionFactory extends Factory
{
    protected $model = AroVersion::class;

    public function definition(): array
    {
        return [
            'code' => 'TEST-'.fake()->unique()->numerify('####'),
            'legal_notice' => 'Test version',
            'effective_from' => '2022-12-31',
            'status' => AroVersion::STATUS_DRAFT,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => AroVersion::STATUS_PUBLISHED, 'published_at' => now()]);
    }
}
