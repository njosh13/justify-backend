<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Aro\Engine\Computed;
use App\Domain\Aro\Engine\CostBasis;
use App\Domain\Aro\Engine\FeeRequest;
use App\Domain\Aro\Engine\Posture;
use App\Domain\Aro\Models\AroItem;
use App\Domain\Aro\Models\AroVersion;
use App\Domain\Aro\Services\FeeCalculator;
use App\Domain\Billing\Models\ChargeableItem;
use App\Enums\FeeAgreementType;
use App\Models\Matter;
use Brick\Money\Money;
use InvalidArgumentException;

/**
 * Builds the engine request for a chargeable line from the matter's
 * classification (basis, scale, posture, certificates, contested), its fee
 * agreements (agreed hourly rate) and the line's own overrides, then prices it.
 */
final class ChargeableItemPricer
{
    public function __construct(private readonly FeeCalculator $calculator) {}

    public function price(Matter $matter, PricingInput $input): Computed
    {
        $matter->loadMissing(['classification.version', 'feeAgreements', 'firm']);

        $classification = $matter->classification;
        $version = $classification->version ?? AroVersion::current()
            ?? throw new PricingException('No ARO version is loaded; seed the catalogue first');

        $item = AroItem::query()
            ->where('aro_version_id', $version->id)
            ->whereKey($input->aroItemId)
            ->first() ?? throw new PricingException('That fee head does not exist in ARO version '.$version->code);

        $basisCents = $input->basisOverrideCents ?? $classification->basis_cents ?? $matter->value_cents;
        $scale = $input->scaleOverride ?? $classification?->scale;
        $hourly = $matter->activeAgreement(FeeAgreementType::Hourly);

        // The matter's posture and certificates describe its instruction fee;
        // they reach only the heads that take them. A per-line override is
        // passed as given so a wrong combination is still refused.
        $postureValue = $input->postureOverride
            ?? ($item->postureTable($scale) === null ? null : $classification?->posture?->value);
        $certificates = $item->schedule === 6 && $item->is_instruction_fee ? $classification?->certificatesForEngine() : null;

        try {
            return $this->calculator->minimum(new FeeRequest(
                version: $version,
                itemCode: $item->code,
                basis: $basisCents === null ? null : Money::ofMinor($basisCents, 'KES'),
                quantity: $input->quantity,
                scale: $scale,
                posture: $postureValue === null ? null : Posture::from($postureValue),
                modifierCodes: $input->modifierCodes,
                modifierAmounts: collect($input->modifierAmounts)->map(fn (int $cents) => Money::ofMinor($cents, 'KES'))->all(),
                certificates: $certificates,
                costBasis: $input->costBasis ?? CostBasis::from($matter->firm->default_cost_basis),
                agreedRate: $hourly?->hourly_rate_cents === null ? null : Money::ofMinor($hourly->hourly_rate_cents, 'KES'),
                instructionFee: $this->instructionFeeFor($matter, $item, $input->excludeItemId),
                contested: (bool) $classification?->contested,
            ));
        } catch (InvalidArgumentException $e) {
            throw new PricingException($e->getMessage(), previous: $e);
        }
    }

    /** Writes the engine result onto the line the way the enforcement layer reads it back. */
    public function apply(ChargeableItem $item, Computed $computed): void
    {
        $item->computed_minimum_cents = $computed->amount->getMinorAmount()->toInt();
        $item->computed_bound = $computed->bound->value;
        $item->computed_ceiling_cents = $computed->ceiling?->getMinorAmount()->toInt();
        $item->computed_snapshot = $computed->toArray();
    }

    public function inputFor(ChargeableItem $item, ?CostBasis $costBasis = null): PricingInput
    {
        return new PricingInput(
            aroItemId: (string) $item->aro_item_id,
            quantity: $item->quantity === null ? null : (float) $item->quantity,
            basisOverrideCents: $item->basis_override_cents,
            scaleOverride: $item->scale_override,
            postureOverride: $item->posture_override?->value,
            modifierCodes: $item->modifier_codes ?? [],
            modifierAmounts: $item->modifier_amounts ?? [],
            costBasis: $costBasis,
            excludeItemId: $item->id,
        );
    }

    /**
     * Getting-up heads derive from "the instruction fee allowed": the amount
     * claimed on the matter's instruction-fee line.
     */
    private function instructionFeeFor(Matter $matter, AroItem $item, ?string $excludeItemId): ?Money
    {
        if ($item->computation !== 'getting_up') {
            return null;
        }

        $instruction = $matter->chargeableItems()
            ->with('aroItem')
            ->when($excludeItemId !== null, fn ($q) => $q->whereKeyNot($excludeItemId))
            ->get()
            ->first(fn (ChargeableItem $c) => (bool) $c->aroItem?->is_instruction_fee);

        if ($instruction === null) {
            throw new PricingException('Add the instruction fee line to this matter first — getting-up is one-third of it');
        }

        return Money::ofMinor($instruction->entered_cents, 'KES');
    }
}
