<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine\Computations;

use App\Domain\Aro\Engine\Computation;
use App\Domain\Aro\Engine\Computed;
use Brick\Money\CurrencyDisplay;
use Brick\Money\Money;

/**
 * "four folios or less 1,100; in excess of four folios, per folio 150" (Sch 6 item 4(a))
 * "per 15 minutes or part thereof 1,000" (Sch 5 Part II item 3) → unitsIncluded 0, includedAmount 0
 *
 * $quantity is already expressed in the item's unit (folios, 15-minute units, km…).
 */
final class PerUnitComputation implements Computation
{
    public function __construct(
        private readonly string $ruleRef,
        private readonly int $unitsIncluded,
        private readonly Money $includedAmount,
        private readonly Money $perAdditionalUnit,
    ) {}

    public function compute(?Money $basis, int|float|null $quantity = null): Computed
    {
        $units = (int) ceil((float) ($quantity ?? 0));
        $c = Computed::zero();

        if ($this->unitsIncluded > 0) {
            $c = $c->add($this->ruleRef, sprintf('first %d units', $this->unitsIncluded), $this->includedAmount);
        }

        $extra = max(0, $units - $this->unitsIncluded);
        if ($extra > 0) {
            $c = $c->add(
                $this->ruleRef,
                sprintf('%d additional units × %s', $extra, $this->perAdditionalUnit->formatToLocale('en_KE', CurrencyDisplay::Code)),
                $this->perAdditionalUnit->multipliedBy($extra),
            );
        }

        return $c;
    }
}
