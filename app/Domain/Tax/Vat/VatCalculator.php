<?php

declare(strict_types=1);

namespace App\Domain\Tax\Vat;

use Brick\Math\RoundingMode;
use Brick\Money\Money;

/**
 * VAT Act 2013 — legal services are standard-rated at 16% (eTIMS taxTyCd B).
 * Fees and recharges are taxable; disbursements paid as agent are out of scope
 * (taxTyCd D); exempt clients are taxTyCd A at 0%.
 */
final class VatCalculator
{
    public const STANDARD_RATE = '0.16';

    /** Taxable base for a bill: professional fees + recharges. */
    public static function taxable(Money $fees, Money $recharges): Money
    {
        return $fees->plus($recharges);
    }

    public static function vat(Money $taxable, bool $exempt = false): Money
    {
        if ($exempt) {
            return Money::zero('KES');
        }

        return $taxable->multipliedBy(self::STANDARD_RATE, RoundingMode::HalfUp);
    }

    public static function taxTypeCode(bool $disbursement, bool $exempt): string
    {
        if ($exempt) {
            return 'A';
        }

        return $disbursement ? 'D' : 'B';
    }
}
