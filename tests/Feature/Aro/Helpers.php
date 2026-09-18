<?php

declare(strict_types=1);

use App\Domain\Aro\Engine\Certificates;
use App\Domain\Aro\Engine\Computed;
use App\Domain\Aro\Engine\CostBasis;
use App\Domain\Aro\Engine\FeeRequest;
use App\Domain\Aro\Engine\Modifiers\ModifierPipeline;
use App\Domain\Aro\Engine\Posture;
use App\Domain\Aro\Engine\Resolver;
use App\Domain\Aro\Models\AroVersion;
use App\Domain\Aro\Services\FeeCalculator;
use Brick\Money\Money;
use Database\Seeders\AroSeeder;

/**
 * Shared helpers for the ARO golden-case suite (plan §11.1). Each test seeds
 * the LN221-2023 catalogue and computes through FeeCalculator, so seed data
 * and engine are validated together (a deliberate deviation from the plan's
 * "pure PHP" wording — see Todo.md "Deviations").
 */
function aroVersion(): AroVersion
{
    return AroVersion::firstOrFail();
}

/**
 * @param  string[]  $modifiers
 * @param  array<string, int|float>  $modifierAmounts
 */
function computed(
    string $code,
    ?float $basis = null,
    ?float $quantity = null,
    ?string $scale = null,
    ?Posture $posture = null,
    array $modifiers = [],
    array $modifierAmounts = [],
    ?Certificates $certificates = null,
    CostBasis $costBasis = CostBasis::PartyParty,
    ?float $agreedRate = null,
    ?float $instructionFee = null,
    bool $contested = false,
): Computed {
    $calc = new FeeCalculator(new Resolver, new ModifierPipeline);

    return $calc->minimum(new FeeRequest(
        version: aroVersion(),
        itemCode: $code,
        basis: $basis === null ? null : Money::of((string) $basis, 'KES'),
        quantity: $quantity,
        scale: $scale,
        posture: $posture,
        modifierCodes: $modifiers,
        modifierAmounts: collect($modifierAmounts)->map(fn ($v) => Money::of((string) $v, 'KES'))->all(),
        certificates: $certificates,
        costBasis: $costBasis,
        agreedRate: $agreedRate === null ? null : Money::of((string) $agreedRate, 'KES'),
        instructionFee: $instructionFee === null ? null : Money::of((string) $instructionFee, 'KES'),
        contested: $contested,
    ));
}

/**
 * @param  string[]  $modifiers
 * @param  array<string, int|float>  $modifierAmounts
 */
function fee(
    string $code,
    ?float $basis = null,
    ?float $quantity = null,
    ?string $scale = null,
    ?Posture $posture = null,
    array $modifiers = [],
    array $modifierAmounts = [],
    ?Certificates $certificates = null,
    CostBasis $costBasis = CostBasis::PartyParty,
    ?float $agreedRate = null,
    ?float $instructionFee = null,
    bool $contested = false,
): float {
    return computed(...func_get_args())->amount->getAmount()->toFloat();
}

function seedAro(): void
{
    (new AroSeeder)->run();
}
