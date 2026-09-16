<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

enum Posture: string
{
    case FullTrial = 'full_trial';
    case Undefended = 'undefended';
    case NoAppearance = 'no_appearance';
    case Summary = 'summary';
    case SettledPreHearing = 'settled_pre_hearing';
}
