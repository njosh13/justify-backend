<?php

declare(strict_types=1);

namespace App\Enums;

enum FeeAgreementType: string
{
    case Hourly = 'hourly';
    case Scale = 'scale';
    case Fixed = 'fixed';
    case Schedule5Election = 'schedule5_election';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Agreed hourly rate (Sch 5 Part I)',
            self::Scale => 'Scale fees',
            self::Fixed => 'Fixed fee',
            self::Schedule5Election => 'Para 22 election to charge under Schedule 5',
        };
    }
}
