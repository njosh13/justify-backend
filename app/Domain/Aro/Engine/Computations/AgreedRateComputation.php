<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine\Computations;

use App\Domain\Aro\Engine\Computation;
use App\Domain\Aro\Engine\Computed;
use Brick\Math\RoundingMode;
use Brick\Money\CurrencyDisplay;
use Brick\Money\Money;
use InvalidArgumentException;

/**
 * Sch 5 Part I para 2: "such hourly rate … as may be agreed with his client".
 * The rate comes from the fee agreement, the quantity is hours (fractions
 * allowed). Para 3 / I-8: the matter total may still not fall below the
 * otherwise-applicable schedule unless a para 22 election was made.
 */
final class AgreedRateComputation implements Computation
{
    public function __construct(
        private readonly string $ruleRef,
        private readonly Money $rate,
    ) {}

    public function compute(?Money $basis, int|float|null $quantity = null): Computed
    {
        if ($quantity === null || $quantity <= 0) {
            throw new InvalidArgumentException("{$this->ruleRef} requires a positive number of hours");
        }

        $hours = (string) $quantity;

        return Computed::zero()->add(
            $this->ruleRef,
            sprintf('%s hours × agreed rate %s', $hours, $this->rate->formatToLocale('en_KE', CurrencyDisplay::Code)),
            $this->rate->multipliedBy($hours, RoundingMode::HalfUp),
        );
    }
}
