<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Aro\Engine\CostBasis;

/**
 * Per-line overrides that feed the engine on top of the matter's
 * classification and fee agreements.
 */
final readonly class PricingInput
{
    /**
     * @param  string[]  $modifierCodes
     * @param  array<string,int>  $modifierAmounts  cents keyed by modifier code
     */
    public function __construct(
        public string $aroItemId,
        public int|float|null $quantity = null,
        public ?int $basisOverrideCents = null,
        public ?string $scaleOverride = null,
        public ?string $postureOverride = null,
        public array $modifierCodes = [],
        public array $modifierAmounts = [],
        public ?CostBasis $costBasis = null,
        public ?string $excludeItemId = null,
    ) {}
}
