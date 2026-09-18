<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine\Computations;

use App\Domain\Aro\Engine\Band;
use App\Domain\Aro\Engine\Computation;
use App\Domain\Aro\Engine\Computed;
use Brick\Math\RoundingMode;
use Brick\Money\CurrencyDisplay;
use Brick\Money\Money;

/**
 * Non-cumulative bracket: the band that contains the basis supplies an
 * explicit base fee (Band::$fixed, read as the fee at the band's lower edge)
 * plus `rate` on the slice above the lower edge.
 *
 * Sch 5 Part II item 7 (debt collection) is the canonical case — the Order
 * states a base per bracket ("Kshs. 50,000 plus 3% of the amount over
 * Kshs 500,000") and the bases are intentionally not continuous with the
 * previous bracket's top.
 */
final class BasePlusRateComputation implements Computation
{
    /** @param Band[] $bands */
    public function __construct(
        private readonly string $ruleRef,
        private readonly array $bands,
    ) {}

    public function compute(?Money $basis, int|float|null $quantity = null): Computed
    {
        if ($basis === null || ! $basis->isPositive()) {
            throw new \InvalidArgumentException("{$this->ruleRef} requires a positive subject-matter value (para 21)");
        }

        foreach ($this->bands as $i => $band) {
            if (! $band->contains($basis)) {
                continue;
            }

            $bandRef = sprintf('%s band %d', $this->ruleRef, $i + 1);
            $c = Computed::zero();

            if ($band->fixed !== null && $band->fixed->isPositive()) {
                $c = $c->add($bandRef, sprintf('base fee for %s', $band->range()), $band->fixed);
            }

            if ($band->rate !== null) {
                $slice = $basis->minus($band->lower);
                $c = $c->add(
                    $bandRef,
                    sprintf('%s%% × %s', rtrim(rtrim(bcmul($band->rate, '100', 4), '0'), '.'), $slice->formatToLocale('en_KE', CurrencyDisplay::Code)),
                    $slice->multipliedBy($band->rate, RoundingMode::HalfUp),
                );
            }

            if ($band->floor !== null && $c->amount->isLessThan($band->floor)) {
                $c = $c->replace($bandRef.' floor', sprintf('or %s whichever is higher', $band->floor->formatToLocale('en_KE', CurrencyDisplay::Code)), $band->floor);
            }

            return $c;
        }

        throw new \LogicException('No band contains basis');
    }
}
