<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Aro\Engine\CostBasis;
use App\Domain\Aro\Models\AroVersion;
use App\Domain\Billing\Models\Bill;
use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Models\Matter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Bill> */
final class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition(): array
    {
        return [
            'matter_id' => Matter::factory(),
            'firm_id' => fn (array $a) => Matter::withoutGlobalScopes()->whereKey($a['matter_id'])->firstOrFail()->firm_id,
            'client_id' => fn (array $a) => Matter::withoutGlobalScopes()->whereKey($a['matter_id'])->firstOrFail()->client_id,
            'type' => BillType::FeeNote,
            'cost_basis' => CostBasis::AdvocateClient,
            'aro_version_id' => fn () => AroVersion::current()->id ?? AroVersion::factory(),
            'status' => BillStatus::Draft,
        ];
    }

    public function issued(string $number = 'FN/2026/0001'): static
    {
        return $this->state([
            'number' => $number,
            'status' => BillStatus::Issued,
            'issued_at' => now(),
            'locked_at' => now(),
            'fees_cents' => 100_000_00,
            'total_cents' => 116_000_00,
            'vat_cents' => 16_000_00,
        ]);
    }
}
