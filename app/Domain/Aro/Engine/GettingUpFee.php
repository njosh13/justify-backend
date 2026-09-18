<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

use Brick\Math\RoundingMode;
use Brick\Money\Money;

/**
 * Sch 6 para 2 (and Sch 8 para 2): not less than one-third of the instruction
 * fee, chargeable only once the hearing is confirmed.
 */
final class GettingUpFee
{
    public static function minimum(Computed $instruction, string $ruleRef = 'Sch 6 para 2'): Computed
    {
        $third = $instruction->amount->dividedBy(3, RoundingMode::HalfUp);

        return Computed::zero()
            ->add($ruleRef, 'getting up — not less than one-third of instruction fee', $third)
            ->markDiscretionary();
    }

    /** Proviso (ii): up to 15% of the instruction fee per adjournment of a confirmed hearing, if the judge so directs. */
    public static function adjournmentCap(Computed $instruction): Money
    {
        return $instruction->amount->multipliedBy('0.15', RoundingMode::HalfUp);
    }
}
