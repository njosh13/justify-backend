<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Aro\Models\AroItem;
use App\Domain\Aro\Models\AroVersion;
use App\Domain\Billing\Models\MatterClassification;
use App\Models\Matter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MatterClassification> */
final class MatterClassificationFactory extends Factory
{
    protected $model = MatterClassification::class;

    public function definition(): array
    {
        return [
            'matter_id' => Matter::factory(),
            'firm_id' => fn (array $attributes) => Matter::withoutGlobalScopes()->whereKey($attributes['matter_id'])->firstOrFail()->firm_id,
            'aro_version_id' => fn () => AroVersion::current()->id ?? AroVersion::factory(),
            'aro_item_id' => null,
            'basis_cents' => null,
            'contested' => false,
            'is_exempt' => false,
            'is_active' => true,
        ];
    }

    public function head(string $code, ?int $basisCents = null): static
    {
        return $this->state(function (array $attributes) use ($code, $basisCents) {
            $versionId = is_string($attributes['aro_version_id'] ?? null) ? $attributes['aro_version_id'] : AroVersion::current()->id;
            $item = AroItem::query()->where('aro_version_id', $versionId)->where('code', $code)->firstOrFail();

            return ['aro_version_id' => $versionId, 'aro_item_id' => $item->id, 'basis_cents' => $basisCents];
        });
    }

    public function exempt(string $reason = 'pro bono'): static
    {
        return $this->state(['is_exempt' => true, 'exemption_reason' => $reason, 'exempted_at' => now()]);
    }
}
