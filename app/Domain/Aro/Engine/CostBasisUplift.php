<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

use Brick\Math\RoundingMode;

/**
 * Sch 6B, 7B, 10B, 11B: advocate-and-client minimum =
 * party-and-party fees increased by 50%.
 */
final class CostBasisUplift
{
    public static function advocateClient(Computed $partyAndParty, string $scheduleRef): Computed
    {
        return $partyAndParty->replace(
            "{$scheduleRef} Part B",
            'advocate and client — increased by 50%',
            $partyAndParty->amount->multipliedBy('1.5', RoundingMode::HalfUp),
        );
    }
}
