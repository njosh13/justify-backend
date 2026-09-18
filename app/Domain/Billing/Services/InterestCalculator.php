<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Carbon\CarbonImmutable;

/**
 * Para 7: simple interest at 14% per annum on costs and disbursements, from the
 * expiry of one month after delivery of the bill, and only where the claim for
 * interest is raised before the bill is paid or tendered in full. Actual/365,
 * rounded once (HALF_UP to the cent) after the whole product.
 */
final class InterestCalculator
{
    public const RATE = '0.14';

    public function accrued(
        Money $principal,
        CarbonImmutable $deliveredAt,
        ?CarbonImmutable $interestClaimedAt,
        ?CarbonImmutable $paidInFullAt,
        CarbonImmutable $asOf,
    ): Money {
        $start = $deliveredAt->addMonthNoOverflow();

        if ($interestClaimedAt === null) {
            return Money::zero('KES');
        }

        if ($paidInFullAt !== null && $interestClaimedAt->greaterThan($paidInFullAt)) {
            return Money::zero('KES');
        }

        $end = $paidInFullAt === null ? $asOf : ($paidInFullAt->lessThan($asOf) ? $paidInFullAt : $asOf);
        if ($end->lessThanOrEqualTo($start)) {
            return Money::zero('KES');
        }

        $days = (int) $start->diffInDays($end);

        return $principal->toRational()
            ->multipliedBy(self::RATE)
            ->multipliedBy($days)
            ->dividedBy(365)
            ->toContext($principal->getContext(), RoundingMode::HalfUp);
    }
}
