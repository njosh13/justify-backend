<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Aro\Engine\CostBasis;
use App\Domain\Aro\Models\AroVersion;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\BillLine;
use App\Domain\Billing\Models\ChargeableItem;
use App\Domain\Tax\Vat\VatCalculator;
use App\Domain\Tax\Vat\WithholdingTaxCalculator;
use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Enums\ChargeableItemKind;
use App\Models\Matter;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Plan §4.13: turns unbilled chargeable work into a draft bill — fee lines
 * recomputed on the bill's cost basis, recharges taxed with the fees,
 * disbursements at the foot (para 69(2)), a blank "attending taxation" line
 * on a bill of costs (para 69(3)), VAT and the WHT memo.
 */
final class BillAssembler
{
    public function __construct(
        private readonly ChargeableItemPricer $pricer,
    ) {}

    /**
     * @param  string[]  $itemIds
     */
    public function draft(Matter $matter, BillType $type, CostBasis $costBasis, array $itemIds, User $user): Bill
    {
        $matter->loadMissing(['firm', 'client', 'classification.version', 'feeAgreements']);

        $items = $matter->chargeableItems()->unbilled()->with('aroItem')->whereKey($itemIds)->get();
        if ($items->isEmpty()) {
            throw new InvalidArgumentException('Select at least one unbilled item to bill');
        }

        $version = $matter->classification->version ?? AroVersion::current()
            ?? throw new InvalidArgumentException('No ARO version is loaded');

        return DB::transaction(function () use ($matter, $type, $costBasis, $items, $user, $version) {
            $bill = new Bill([
                'matter_id' => $matter->id,
                'client_id' => $matter->client_id,
                'type' => $type,
                'cost_basis' => $costBasis,
                'aro_version_id' => $version->id,
                'status' => BillStatus::Draft,
            ]);
            $bill->firm_id = $matter->firm_id;
            $bill->save();

            $lines = $this->buildLines($bill, $matter, $items);
            foreach ($lines as $line) {
                $bill->lines()->create($line);
            }

            $this->applyTotals($bill, $lines, $matter);
            $bill->computed_snapshot = $this->snapshot($bill, $matter, $lines);
            $bill->save();

            ChargeableItem::query()->whereKey($items->modelKeys())->update(['bill_id' => $bill->id]);

            $bill->recordEvent('drafted', $user, ['items' => $items->count()]);

            return $bill->refresh();
        });
    }

    /**
     * @param  Collection<int, ChargeableItem>  $items
     * @return list<array<string,mixed>>
     */
    private function buildLines(Bill $bill, Matter $matter, Collection $items): array
    {
        $firm = $matter->firm;
        $client = $matter->client;
        $vatApplies = $firm->vat_registered;
        $exempt = $client->is_vat_exempt;
        $escape = $matter->isExemptFromScale() || $matter->hasCommunicatedElection();
        $pricedBasis = CostBasis::from($firm->default_cost_basis);

        $fees = [];
        $disbursements = [];

        foreach ($items as $item) {
            $section = $item->kind === ChargeableItemKind::Disbursement ? BillLine::SECTION_DISBURSEMENTS : BillLine::SECTION_FEES;
            $claimed = $item->entered_cents;
            $provenance = null;
            $ruleRef = null;

            if ($item->isPricedByEngine()) {
                $computed = $this->pricer->price($matter, $this->pricer->inputFor($item, $bill->cost_basis));
                $minimum = $computed->amount->getMinorAmount()->toInt();
                $ruleRef = $item->aroItem?->rule_reference;
                $provenance = $computed->provenance();

                if ($computed->isMaximum()) {
                    $claimed = min($item->entered_cents, $minimum);
                } elseif ($bill->cost_basis !== $pricedBasis) {
                    $claimed = $minimum;
                    $provenance .= '; drawn '.($bill->cost_basis === CostBasis::PartyParty ? 'party and party' : 'advocate and client').' at scale';
                } elseif (! $escape) {
                    $claimed = max($item->entered_cents, $minimum);
                }
            }

            $claimed = LineRounding::cents($claimed, $firm);

            $taxable = $section === BillLine::SECTION_FEES && $vatApplies && ! $exempt;
            $vatCents = $taxable ? LineRounding::cents(VatCalculator::vat(Money::ofMinor($claimed, 'KES'))->getMinorAmount()->toInt(), $firm) : 0;

            $line = [
                'firm_id' => $bill->firm_id,
                'dated_on' => $item->occurred_on->toDateString(),
                'particulars' => $item->kind === ChargeableItemKind::Recharge ? 'Recharge — '.$item->description : $item->description,
                'claimed_cents' => $claimed,
                'aro_item_id' => $item->aro_item_id,
                'rule_reference' => $ruleRef,
                'provenance' => $provenance,
                'chargeable_item_id' => $item->id,
                'section' => $section,
                'vat_rate' => $taxable ? VatCalculator::STANDARD_RATE : '0',
                'vat_cents' => $vatCents,
                'tax_type_code' => $vatApplies ? VatCalculator::taxTypeCode($section === BillLine::SECTION_DISBURSEMENTS, $exempt) : null,
                'kind' => $item->kind->value,
            ];

            $section === BillLine::SECTION_FEES ? $fees[] = $line : $disbursements[] = $line;
        }

        $sort = fn (array $a, array $b) => strcmp($a['dated_on'], $b['dated_on']);
        usort($fees, $sort);
        usort($disbursements, $sort);

        $lines = [...$fees, ...$disbursements];

        if ($bill->type === BillType::BillOfCosts) {
            $lines[] = [
                'firm_id' => $bill->firm_id,
                'dated_on' => null,
                'particulars' => 'Attending taxation',
                'claimed_cents' => 0,
                'aro_item_id' => null,
                'rule_reference' => 'para 69(3)',
                'provenance' => 'amount left blank for completion by the taxing officer',
                'chargeable_item_id' => null,
                'section' => BillLine::SECTION_TAXATION_ATTENDANCE,
                'vat_rate' => '0',
                'vat_cents' => 0,
                'tax_type_code' => null,
                'kind' => null,
            ];
        }

        foreach ($lines as $i => &$line) {
            $line['seq'] = $i + 1;
        }

        return $lines;
    }

    /** @param list<array<string,mixed>> $lines */
    private function applyTotals(Bill $bill, array $lines, Matter $matter): void
    {
        $fees = 0;
        $recharges = 0;
        $disbursements = 0;
        $vat = 0;

        foreach ($lines as $line) {
            $vat += $line['vat_cents'];
            match (true) {
                $line['section'] === BillLine::SECTION_DISBURSEMENTS => $disbursements += $line['claimed_cents'],
                $line['kind'] === ChargeableItemKind::Recharge->value => $recharges += $line['claimed_cents'],
                default => $fees += $line['claimed_cents'],
            };
        }

        $wht = WithholdingTaxCalculator::expected(Money::ofMinor($fees + $recharges, 'KES'), $matter->client->is_withholding_agent);

        $bill->fees_cents = $fees;
        $bill->recharges_cents = $recharges;
        $bill->disbursements_cents = $disbursements;
        $bill->vat_cents = $vat;
        $bill->wht_expected_cents = LineRounding::cents($wht->getMinorAmount()->toInt(), $matter->firm);
        $bill->total_cents = $fees + $recharges + $disbursements + $vat;
    }

    /**
     * @param  list<array<string,mixed>>  $lines
     * @return array<string,mixed>
     */
    private function snapshot(Bill $bill, Matter $matter, array $lines): array
    {
        $bill->loadMissing('version');

        return [
            'aro_version' => $bill->version->code,
            'cost_basis' => $bill->cost_basis->value,
            'type' => $bill->type->value,
            'rounding_policy' => $matter->firm->rounding_policy,
            'firm' => $matter->firm->only(['name', 'kra_pin', 'lsk_firm_number', 'address', 'email', 'phone', 'vat_registered']),
            'client' => $matter->client->only(['full_name', 'kra_pin', 'address', 'email', 'is_withholding_agent', 'is_vat_exempt', 'vat_exemption_reference']),
            'matter' => $matter->only(['title', 'reference', 'court_level', 'cause_number']),
            'classification' => $matter->classification?->only(['basis_cents', 'basis_limb', 'scale', 'posture', 'certificates', 'contested', 'is_exempt']),
            'lines' => array_map(fn (array $l) => collect($l)->except(['firm_id'])->all(), $lines),
            'totals' => [
                'fees_cents' => $bill->fees_cents,
                'recharges_cents' => $bill->recharges_cents,
                'disbursements_cents' => $bill->disbursements_cents,
                'vat_cents' => $bill->vat_cents,
                'wht_expected_cents' => $bill->wht_expected_cents,
                'total_cents' => $bill->total_cents,
            ],
        ];
    }
}
