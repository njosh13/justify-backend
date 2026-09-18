<?php

declare(strict_types=1);

namespace App\Enums;

enum ChargeableItemKind: string
{
    case Fee = 'fee';
    case Time = 'time';
    case Disbursement = 'disbursement';
    case Recharge = 'recharge';

    public function isProfessionalFee(): bool
    {
        return in_array($this, [self::Fee, self::Time], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Fee => 'Professional fee',
            self::Time => 'Time',
            self::Disbursement => 'Disbursement (paid as agent)',
            self::Recharge => 'Recharge (firm expense passed on)',
        };
    }
}
