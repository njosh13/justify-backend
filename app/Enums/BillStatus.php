<?php

declare(strict_types=1);

namespace App\Enums;

enum BillStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Delivered = 'delivered';
    case Disputed = 'disputed';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Voided = 'voided';

    public function isLocked(): bool
    {
        return $this !== self::Draft;
    }
}
