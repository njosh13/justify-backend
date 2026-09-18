<?php

declare(strict_types=1);

namespace App\Enums;

enum BillType: string
{
    case FeeNote = 'fee_note';
    case BillOfCosts = 'bill_of_costs';

    public function label(): string
    {
        return match ($this) {
            self::FeeNote => 'Fee note',
            self::BillOfCosts => 'Bill of costs (para 69)',
        };
    }
}
