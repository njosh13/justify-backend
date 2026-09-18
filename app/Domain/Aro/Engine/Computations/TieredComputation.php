<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine\Computations;

use App\Domain\Aro\Engine\Band;
use App\Domain\Aro\Engine\Computation;
use App\Domain\Aro\Engine\Computed;
use Brick\Math\RoundingMode;
use Brick\Money\CurrencyDisplay;
use Brick\Money\Money;
use InvalidArgumentException;
use LogicException;

/**
 * Cumulative bands: the fee at the top of the previous band plus `rate` on the
 * slice inside this band, or a fixed bracket fee where the Order prescribes
 * one ("fees as for Kshs. 1,000,000 plus an additional 2%").
 */
final class TieredComputation implements Computation
{
    /** @param Band[] $bands ascending, contiguous */
    public function __construct(
        private readonly string $ruleRef,
        private readonly array $bands,
    ) {}

    public function compute(?Money $basis, int|float|null $quantity = null): Computed
    {
        if ($basis === null || ! $basis->isPositive()) {
            throw new InvalidArgumentException("{$this->ruleRef} requires a positive subject-matter value (para 21)");
        }

        $result = Computed::zero();
        $carry = null;

        foreach ($this->bands as $i => $band) {
            if ($basis->isLessThanOrEqualTo($band->lower)) {
                break;
            }

            $bandRef = sprintf('%s band %d', $this->ruleRef, $i + 1);

            if ($band->fixed !== null) {
                if ($band->contains($basis)) {
                    return $result->replace($bandRef, sprintf('fixed fee for %s', $band->range()), $band->fixed);
                }

                $carry = [$bandRef, $band->upper, $band->fixed];

                continue;
            }

            if ($band->rate === null) {
                throw new LogicException("{$bandRef} has neither fixed fee nor rate");
            }

            if ($carry !== null) {
                [$carryRef, $carryUpper, $carryFixed] = $carry;
                $result = $result->replace($carryRef, sprintf('fee as for %s', self::fmt($carryUpper)), $carryFixed);
                $carry = null;
            }

            $top = $band->upper === null ? $basis : Money::min($basis, $band->upper);
            $slice = $top->minus($band->lower);
            $fee = $slice->multipliedBy($band->rate, RoundingMode::HalfUp);

            $result = $result->add(
                $bandRef,
                sprintf('%s%% × %s', rtrim(rtrim(bcmul($band->rate, '100', 4), '0'), '.'), self::fmt($slice)),
                $fee,
            );

            if ($band->floor !== null && $result->amount->isLessThan($band->floor)) {
                $result = $result->replace($bandRef.' floor', sprintf('or %s whichever is higher', self::fmt($band->floor)), $band->floor);
            }
        }

        return $result;
    }

    private static function fmt(?Money $money): string
    {
        return $money === null ? 'open' : $money->formatToLocale('en_KE', CurrencyDisplay::Code);
    }
}
