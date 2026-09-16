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
    ) {}
}
