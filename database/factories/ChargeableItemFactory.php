<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Aro\Models\AroItem;
use App\Domain\Billing\Models\ChargeableItem;
use App\Enums\ChargeableItemKind;
use App\Models\Matter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChargeableItem> */
final class ChargeableItemFactory extends Factory
{
    protected $model = ChargeableItem::class;

    public function definition(): array
    {
        return [
            'matter_id' => Matter::factory(),
            'firm_id' => fn (array $a) => Matter::withoutGlobalScopes()->whereKey($a['matter_id'])->firstOrFail()->firm_id,
            'kind' => ChargeableItemKind::Disbursement,
            'description' => 'Court filing fees',
            'occurred_on' => now()->toDateString(),
            'entered_cents' => 20_000_00,
            'is_billable' => true,
        ];
    }

    public function disbursement(int $cents, string $description = 'Court filing fees'): static
    {
        return $this->state(['kind' => ChargeableItemKind::Disbursement, 'entered_cents' => $cents, 'description' => $description]);
    }

    public function recharge(int $cents, string $description = 'Photocopying'): static
    {
        return $this->state(['kind' => ChargeableItemKind::Recharge, 'entered_cents' => $cents, 'description' => $description]);
    }

    /** A fee line already priced by the engine, as the pricer would store it. */
    public function pricedFee(string $code, int $enteredCents, int $minimumCents, ?int $basisCents = null): static
    {
        return $this->state(function () use ($code, $enteredCents, $minimumCents, $basisCents) {
            $item = AroItem::query()->where('code', $code)->firstOrFail();

            return [
                'kind' => ChargeableItemKind::Fee,
                'aro_item_id' => $item->id,
                'description' => $item->label,
                'basis_override_cents' => $basisCents,
                'entered_cents' => $enteredCents,
                'computed_minimum_cents' => $minimumCents,
                'computed_bound' => 'prescribed',
                'computed_snapshot' => ['amount_cents' => $minimumCents, 'bound' => 'prescribed', 'ceiling_cents' => null, 'provenance' => $item->rule_reference, 'steps' => []],
            ];
        });
    }
}
