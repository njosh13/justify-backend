<?php

declare(strict_types=1);

use App\Domain\Aro\Engine\Certificates;
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
 * the published LN221-2023 catalogue and computes through FeeCalculator, so
 * seed data and engine are validated together.
 */
function aroVersion(): AroVersion
{
    return AroVersion::firstOrFail();
}

function fee(
    string $code,
    ?float $basis = null,
    ?float $quantity = null,
    ?string $scale = null,
    ?Posture $posture = null,
    array $modifiers = [],
    ?Certificates $certificates = null,
    CostBasis $costBasis = CostBasis::PartyParty,
): float {
    $calc = new FeeCalculator(new Resolver, new ModifierPipeline);

    $computed = $calc->minimum(new FeeRequest(
        version: aroVersion(),
        itemCode: $code,
        basis: $basis === null ? null : Money::of((string) $basis, 'KES'),
        quantity: $quantity,
        scale: $scale,
        posture: $posture,
        modifierCodes: $modifiers,
        certificates: $certificates,
        costBasis: $costBasis,
    ));

    return (float) $computed->amount->getAmount()->toFloat();
}

function seedAro(): void
{
    (new AroSeeder)->run();
}
