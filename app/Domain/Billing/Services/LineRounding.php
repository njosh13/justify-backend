<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Models\Firm;
use Brick\Math\RoundingMode;
use Brick\Money\Money;

/**
 * I-7: compute in cents, round each bill line HALF_UP to the whole shilling
 * (or keep cents when the firm has opted out). Totals are sums of rounded lines.
 */
final class LineRounding
{
    public static function apply(Money $amount, Firm $firm): Money
    {
        if (! $firm->roundsToShilling()) {
            return $amount;
        }

        return Money::of($amount->getAmount()->toScale(0, RoundingMode::HalfUp), 'KES');
    }

    public static function cents(int $cents, Firm $firm): int
    {
        return self::apply(Money::ofMinor($cents, 'KES'), $firm)->getMinorAmount()->toInt();
    }
}
