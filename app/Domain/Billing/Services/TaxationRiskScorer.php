<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use Brick\Money\Money;

final class TaxationRiskScorer
{
    /** Para 77: true when more than one-sixth of the bill (excluding court fees) is taxed off. */
    public static function oneSixthBreached(Money $claimedExCourtFees, Money $taxedOff): bool
    {
        return $taxedOff->multipliedBy(6)->isGreaterThan($claimedExCourtFees);
    }
}
