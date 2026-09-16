<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

use Brick\Math\RoundingMode;

final class PostureMultiplier
{
    /**
     * Sch 6/7 item 1 (a)–(c), as interpreted at I-1: the percentages are
     * multipliers on the undefended (a) and defended (b) tables respectively.
     */
    public static function apply(Computed $instruction, Posture $posture): Computed
    {
        return match ($posture) {
            Posture::NoAppearance => $instruction->replace('Sch 6/7 item 1(a)', '65% — no appearance entered', $instruction->amount->multipliedBy('0.65', RoundingMode::HalfUp)),
            Posture::Summary => $instruction->replace('Sch 6/7 item 1(b)', '75% — determined summarily', $instruction->amount->multipliedBy('0.75', RoundingMode::HalfUp)),
            Posture::SettledPreHearing => $instruction->replace('Sch 6/7 item 1(c)', '85% — settled before first hearing confirmed', $instruction->amount->multipliedBy('0.85', RoundingMode::HalfUp)),
            default => $instruction,
        };
    }
}
