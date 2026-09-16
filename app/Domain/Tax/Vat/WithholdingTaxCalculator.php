<?php

declare(strict_types=1);

namespace App\Domain\Tax\Vat;

use Brick\Math\RoundingMode;
use Brick\Money\Money;

/**
 * Income Tax Act s.35 — resident withholding agents deduct 5% WHT on
 * professional fees paid to a resident advocate, where aggregate monthly
 * payments exceed KES 24,000. Verify rate/threshold before release (§14.8).
 */
final class WithholdingTaxCalculator
{
    public const RATE = '0.05';

    public const MONTHLY_THRESHOLD = 24_000;

    /**
     * @param  Money  $taxableFees  professional fees + recharges this bill
     * @param  Money  $monthlyAggregateFees  total fees paid to the firm by this client in the month
     */
    public static function expected(Money $taxableFees, bool $clientIsWithholdingAgent, ?Money $monthlyAggregateFees = null): Money
    {
        if (! $clientIsWithholdingAgent) {
            return Money::zero('KES');
        }

        $aggregate = $monthlyAggregateFees ?? $taxableFees;
        if ($aggregate->isLessThanOrEqualTo(Money::of(self::MONTHLY_THRESHOLD, 'KES'))) {
            return Money::zero('KES');
        }

        return $taxableFees->multipliedBy(self::RATE, RoundingMode::HalfUp);
    }
}
