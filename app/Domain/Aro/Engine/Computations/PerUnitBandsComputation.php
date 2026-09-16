<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine\Computations;

use App\Domain\Aro\Engine\Computation;
use App\Domain\Aro\Engine\Computed;
use Brick\Money\CurrencyDisplay;
use Brick\Money\Money;

/**
 * Basis-derived units charged at tiered per-unit rates.
 *
 * Sch 1 Third Scale (negotiation): basis divided into KES 2,000 units —
 * "first 100 units @112; next 200 @52; thereafter @30".
 * Sch 10 item 1(g) (inventory): 2,103 per KES 20,000 of net estate,
 * multiplied by the number of entries, floor 3,000.
 *
 * Remainder rule: a remainder of one-half of a unit or less counts as half a
 * unit; more than half counts as a full unit.
 */
final class PerUnitBandsComputation implements Computation
{
    /**
     * @param  array<int, array{upper_units: int|float|null, per_unit: Money}>  $unitBands
     *                                                                                      ascending by unit count; last band open (upper_units null)
     */
    public function __construct(
        private readonly string $ruleRef,
        private readonly int $unitSizeCents,
        private readonly int $halfUnitThresholdCents,
        private readonly array $unitBands,
        private readonly ?Money $floor = null,
    ) {}

    public function compute(?Money $basis, int|float|null $quantity = null): Computed
    {
        if ($basis === null) {
            throw new \InvalidArgumentException("{$this->ruleRef} requires a subject-matter value");
        }

        $units = $this->units($basis->getMinorAmount()->toInt());
        if ($quantity !== null) {
            $units *= (float) $quantity;
        }

        $result = Computed::zero();

        $lowerUnit = 0.0;
        $remaining = $units;
        foreach ($this->unitBands as $i => $band) {
            if ($remaining <= 0) {
                break;
            }
            $capacity = $band['upper_units'] === null ? $remaining : min($remaining, (float) $band['upper_units'] - $lowerUnit);
            $fee = $band['per_unit']->multipliedBy((string) $capacity);

            $result = $result->add(
                sprintf('%s units band %d', $this->ruleRef, $i + 1),
                sprintf('%s units × %s', rtrim(rtrim(number_format($capacity, 1), '0'), '.'), $band['per_unit']->formatToLocale('en_KE', CurrencyDisplay::Code)),
                $fee,
            );

            $remaining -= $capacity;
            $lowerUnit = (float) ($band['upper_units'] ?? $lowerUnit);
        }

        if ($this->floor !== null && $result->amount->isLessThan($this->floor)) {
            $result = $result->replace(
                $this->ruleRef.' floor',
                sprintf('or %s whichever is higher', $this->floor->formatToLocale('en_KE', CurrencyDisplay::Code)),
                $this->floor,
            );
        }

        return $result;
    }

    private function units(int $basisCents): float
    {
        $whole = intdiv($basisCents, $this->unitSizeCents);
        $remainder = $basisCents % $this->unitSizeCents;

        if ($remainder === 0) {
            return (float) $whole;
        }

        return $whole + ($remainder <= $this->halfUnitThresholdCents ? 0.5 : 1.0);
    }
}
