<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

/**
 * Sch 6 / Sch 7 item 1: the 65% multiplier attaches to the undefended table
 * (a); the 75% and 85% multipliers attach to the defended table (b).
 */
enum PostureTable: string
{
    case Undefended = 'a';
    case Defended = 'b';

    /** @return Posture[] */
    public function postures(): array
    {
        return match ($this) {
            self::Undefended => [Posture::Undefended, Posture::NoAppearance],
            self::Defended => [Posture::FullTrial, Posture::Summary, Posture::SettledPreHearing],
        };
    }

    public function defaultPosture(): Posture
    {
        return match ($this) {
            self::Undefended => Posture::Undefended,
            self::Defended => Posture::FullTrial,
        };
    }

    public function accepts(Posture $posture): bool
    {
        return in_array($posture, $this->postures(), true);
    }
}
