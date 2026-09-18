<?php

declare(strict_types=1);

namespace App\Enums;

enum CourtLevel: string
{
    case None = 'none';
    case HighCourt = 'high_court';
    case Subordinate = 'subordinate';
    case TribunalLandlordTenant = 'tribunal_lt';
    case TribunalRentRestriction = 'tribunal_rr';
    case TribunalOther = 'tribunal_other';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Non-contentious (no court)',
            self::HighCourt => 'High Court',
            self::Subordinate => 'Subordinate court',
            self::TribunalLandlordTenant => 'Business Premises Rent Tribunal (Cap. 301)',
            self::TribunalRentRestriction => 'Rent Restriction Tribunal (Cap. 296)',
            self::TribunalOther => 'Other tribunal',
        };
    }

    /** The Order's schedule that governs litigation costs at this level. */
    public function schedule(): ?int
    {
        return match ($this) {
            self::None => null,
            self::HighCourt => 6,
            self::Subordinate => 7,
            self::TribunalLandlordTenant => 8,
            self::TribunalRentRestriction => 9,
            self::TribunalOther => 11,
        };
    }
}
