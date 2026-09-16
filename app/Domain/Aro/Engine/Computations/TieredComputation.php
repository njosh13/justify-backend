<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine\Computations;

use App\Domain\Aro\Engine\Band;
use App\Domain\Aro\Engine\Computation;
use App\Domain\Aro\Engine\Computed;
use Brick\Math\RoundingMode;
use Brick\Money\CurrencyDisplay;
use Brick\Money\Money;

final class TieredComputation implements Computation
{
    /** @param Band[] $bands ascending, contiguous */
    public function __construct(
        private readonly string $ruleRef,
        private readonly array $bands,
    ) {}

    public function compute(?Money $basis, int|float|null $quantity = null): Computed
    {
        if ($basis === null) {
            throw new \InvalidArgumentException("{$this->ruleRef} requires a subject-matter value");
        }

        $result = Computed::zero();

        foreach ($this->bands as $i => $band) {
            if ($basis->isLessThanOrEqualTo($band->lower)) {
                break;
            }

            $bandRef = sprintf('%s band %d', $this->ruleRef, $i + 1);

            if ($band->fixed !== null && $band->contains($basis)) {
                return $result->replace($bandRef, sprintf('fixed fee for %s', $band->range()), $band->fixed);
            }

            if ($band->fixed !== null) {
                $result = $result->replace($bandRef, sprintf('fee as for %s', $band->upper->formatToLocale('en_KE', CurrencyDisplay::Code)), $band->fixed);

                continue;
            }

            if ($band->rate === null) {
                throw new \LogicException("{$bandRef} has neither fixed fee nor rate");
            }

            $top = $band->upper === null ? $basis : Money::min($basis, $band->upper);
            $slice = $top->minus($band->lower);
            $fee = $slice->multipliedBy($band->rate, RoundingMode::HalfUp);

            $result = $result->add(
                $bandRef,
                sprintf('%s%% × %s', rtrim(rtrim(bcmul($band->rate, '100', 4), '0'), '.'), $slice->formatToLocale('en_KE', CurrencyDisplay::Code)),
                $fee,
            );

            if ($band->floor !== null && $result->amount->isLessThan($band->floor)) {
                $result = $result->replace($bandRef.' floor', sprintf('or %s whichever is higher', $band->floor->formatToLocale('en_KE', CurrencyDisplay::Code)), $band->floor);
            }
        }

        return $result;
    }
}
