<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\ChargeableItem;
use App\Models\Matter;
use Illuminate\Support\Collection;

/**
 * Para 3 roll-up (plan §4.10): per-line shortfall against the stored
 * statutory minimum and the matter total, with the two lawful escape hatches
 * (exempt classification, para 22 election communicated in writing).
 */
final class ShortfallCalculator
{
    /**
     * @param  Collection<int, ChargeableItem>|null  $items  defaults to the matter's unbilled lines
     * @return array{items: array<int, array{id:string, description:string, entered_cents:int, minimum_cents:int, shortfall_cents:int}>, total_cents:int, exempt:bool, election:bool, blocking:bool}
     */
    public function forMatter(Matter $matter, ?Collection $items = null): array
    {
        $matter->loadMissing(['classification', 'feeAgreements']);

        $items ??= $matter->chargeableItems()->unbilled()->get();

        $rows = $items
            ->filter(fn (ChargeableItem $i) => $i->isPricedByEngine() && $i->computed_minimum_cents !== null)
            ->map(fn (ChargeableItem $i) => [
                'id' => $i->id,
                'description' => $i->description,
                'entered_cents' => $i->entered_cents,
                'minimum_cents' => (int) $i->computed_minimum_cents,
                'shortfall_cents' => $i->shortfallCents(),
            ])
            ->values();

        $total = (int) $rows->sum('shortfall_cents');
        $exempt = $matter->isExemptFromScale();
        $election = $matter->hasCommunicatedElection();

        return [
            'items' => $rows->all(),
            'total_cents' => $total,
            'exempt' => $exempt,
            'election' => $election,
            'blocking' => $total > 0 && ! $exempt && ! $election,
        ];
    }
}
