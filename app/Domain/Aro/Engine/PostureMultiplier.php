<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class PostureMultiplier
{
    /**
     * Sch 6/7 item 1 (a)–(c), as interpreted at I-1: 65% reduces the
     * undefended table (a); 75% and 85% reduce the defended table (b). A
     * posture that belongs to the other table is rejected rather than
     * silently applied.
     */
    public static function apply(Computed $instruction, Posture $posture, PostureTable $table): Computed
    {
        if (! $table->accepts($posture)) {
            throw new InvalidArgumentException(sprintf(
                "Posture '%s' does not apply to the %s table (item 1(%s)); it belongs to item 1(%s)",
                $posture->value,
                $table === PostureTable::Undefended ? 'undefended' : 'defended',
                $table->value,
                $table === PostureTable::Undefended ? 'b' : 'a',
            ));
        }

        return match ($posture) {
            Posture::NoAppearance => $instruction->replace('Sch 6/7 item 1(a)', '65% — no appearance entered', $instruction->amount->multipliedBy('0.65', RoundingMode::HalfUp)),
            Posture::Summary => $instruction->replace('Sch 6/7 item 1(b)', '75% — determined summarily', $instruction->amount->multipliedBy('0.75', RoundingMode::HalfUp)),
            Posture::SettledPreHearing => $instruction->replace('Sch 6/7 item 1(c)', '85% — settled before first hearing confirmed', $instruction->amount->multipliedBy('0.85', RoundingMode::HalfUp)),
            Posture::FullTrial, Posture::Undefended => $instruction,
        };
    }
}
