<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Models\FeeAgreement;
use App\Enums\FeeAgreementType;
use App\Models\Matter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FeeAgreement> */
final class FeeAgreementFactory extends Factory
{
    protected $model = FeeAgreement::class;

    public function definition(): array
    {
        return [
            'matter_id' => Matter::factory(),
            'firm_id' => fn (array $a) => Matter::withoutGlobalScopes()->whereKey($a['matter_id'])->firstOrFail()->firm_id,
            'client_id' => fn (array $a) => Matter::withoutGlobalScopes()->whereKey($a['matter_id'])->firstOrFail()->client_id,
            'type' => FeeAgreementType::Scale,
            'is_active' => true,
        ];
    }

    public function hourly(int $rateCents): static
    {
        return $this->state(['type' => FeeAgreementType::Hourly, 'hourly_rate_cents' => $rateCents, 'signed_at' => now()]);
    }

    public function schedule5Election(bool $communicated = true): static
    {
        return $this->state([
            'type' => FeeAgreementType::Schedule5Election,
            'election_communicated_at' => $communicated ? now() : null,
        ]);
    }
}
