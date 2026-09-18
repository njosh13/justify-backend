<?php

declare(strict_types=1);

namespace App\Enums;

enum FirmRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Advocate = 'advocate';
    case Accounts = 'accounts';
    case ReadOnly = 'readonly';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Advocate => 'Advocate',
            self::Accounts => 'Accounts',
            self::ReadOnly => 'Read only',
        };
    }

    public function canManageFirm(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canBill(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Advocate], true);
    }

    public function canRecordPayments(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Accounts], true);
    }

    public function canWrite(): bool
    {
        return $this !== self::ReadOnly;
    }
}
