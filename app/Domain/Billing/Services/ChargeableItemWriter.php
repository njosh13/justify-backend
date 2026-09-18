<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Aro\Engine\FeeBound;
use App\Domain\Billing\Models\ChargeableItem;
use App\Models\Matter;
use App\Models\User;
use Brick\Money\CurrencyDisplay;
use Brick\Money\Money;
use Illuminate\Validation\ValidationException;

/**
 * Plan §10 "server recomputes computed_minimum on every write": prices a
 * fee/time line, stores the snapshot, and enforces para 3 (never below scale
 * without exemption/election), ceilings ("not exceeding"), and the
 * justification an advocate must record for charging above a floor.
 */
final class ChargeableItemWriter
{
    public function __construct(private readonly ChargeableItemPricer $pricer) {}

    /** @param array<string,mixed> $attributes */
    public function store(Matter $matter, array $attributes, User $user): ChargeableItem
    {
        $item = new ChargeableItem($attributes);
        $item->matter_id = $matter->id;
        $item->firm_id = $matter->firm_id;
        $item->advocate_id = $user->id;

        $this->priceAndEnforce($matter, $item);
        $item->save();

        return $item;
    }

    /** @param array<string,mixed> $attributes */
    public function update(ChargeableItem $item, array $attributes): ChargeableItem
    {
        $item->fill($attributes);

        $this->priceAndEnforce($item->matter, $item);
        $item->save();

        return $item;
    }

    private function priceAndEnforce(Matter $matter, ChargeableItem $item): void
    {
        $matter->loadMissing(['classification', 'feeAgreements', 'firm']);

        if (! $item->kind->isProfessionalFee()) {
            $item->aro_item_id = null;
            $item->computed_minimum_cents = null;
            $item->computed_bound = null;
            $item->computed_ceiling_cents = null;
            $item->computed_snapshot = null;

            return;
        }

        if ($item->aro_item_id === null) {
            throw ValidationException::withMessages(['aro_item_id' => 'Choose the fee head from the Order for a professional fee line.']);
        }

        try {
            $computed = $this->pricer->price($matter, $this->pricer->inputFor($item));
        } catch (PricingException $e) {
            throw ValidationException::withMessages(['pricing' => $e->getMessage()]);
        }

        $this->pricer->apply($item, $computed);

        $entered = $item->entered_cents;
        $amount = $computed->amount->getMinorAmount()->toInt();
        $fmt = fn (int $cents) => Money::ofMinor($cents, 'KES')->formatToLocale('en_KE', CurrencyDisplay::Code);
        $ruleRef = $item->aroItem->rule_reference ?? '';

        if ($computed->ceiling !== null && $entered > $computed->ceiling->getMinorAmount()->toInt()) {
            throw ValidationException::withMessages(['entered' => sprintf('Exceeds the statutory maximum of %s (%s).', $fmt($computed->ceiling->getMinorAmount()->toInt()), $ruleRef)]);
        }

        if ($computed->bound === FeeBound::Maximum) {
            if ($entered > $amount) {
                throw ValidationException::withMessages(['entered' => sprintf('Exceeds the statutory maximum of %s (%s).', $fmt($amount), $ruleRef)]);
            }

            return;
        }

        $escape = $matter->isExemptFromScale() || $matter->hasCommunicatedElection();
        if ($entered < $amount && ! $escape) {
            throw ValidationException::withMessages(['entered' => sprintf(
                'Below the statutory minimum of %s (%s). Para 3: an advocate may not agree to less than the Order provides. Raise the fee, record an exemption on the matter, or communicate a para 22 election in writing.',
                $fmt($amount),
                $ruleRef,
            )]);
        }

        if ($entered > $amount && blank($item->uplift_justification)) {
            throw ValidationException::withMessages(['uplift_justification' => sprintf(
                'The Order gives %s for this head (%s). Record why %s is charged — this is what you will justify on taxation.',
                $fmt($amount),
                $ruleRef,
                $fmt($entered),
            )]);
        }
    }
}
