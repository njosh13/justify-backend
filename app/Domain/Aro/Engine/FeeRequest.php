<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

use App\Domain\Aro\Models\AroVersion;
use Brick\Money\Money;

final readonly class FeeRequest
{
    /**
     * @param  string[]  $modifierCodes
     * @param  array<string, Money>  $modifierAmounts  per-case amounts for add/floor/cap modifiers keyed by code (e.g. para 4/5 discretionary additions)
     * @param  Money|null  $agreedRate  Sch 5 Part I hourly rate from the fee agreement; required by `agreed_rate` heads
     * @param  Money|null  $instructionFee  the instruction fee allowed; required by `getting_up` heads
     * @param  bool  $contested  whether the matter is contested — drives Sch 10 Part B, which applies "in contested matter" only
     */
    public function __construct(
        public AroVersion $version,
        public string $itemCode,
        public ?Money $basis = null,
        public int|float|null $quantity = null,
        public ?string $scale = null,
        public ?Posture $posture = null,
        public array $modifierCodes = [],
        public array $modifierAmounts = [],
        public ?Certificates $certificates = null,
        public CostBasis $costBasis = CostBasis::PartyParty,
        public ?Money $agreedRate = null,
        public ?Money $instructionFee = null,
        public bool $contested = false,
    ) {}
}
