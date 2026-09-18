<?php

declare(strict_types=1);

namespace App\Domain\Aro\Services;

use App\Domain\Aro\Engine\Certificates;
use App\Domain\Aro\Engine\Computed;
use App\Domain\Aro\Engine\CostBasis;
use App\Domain\Aro\Engine\CostBasisUplift;
use App\Domain\Aro\Engine\FeeRequest;
use App\Domain\Aro\Engine\Modifiers\Modifier;
use App\Domain\Aro\Engine\Modifiers\ModifierOp;
use App\Domain\Aro\Engine\Modifiers\ModifierPipeline;
use App\Domain\Aro\Engine\PostureMultiplier;
use App\Domain\Aro\Engine\PostureTable;
use App\Domain\Aro\Engine\Resolver;
use App\Domain\Aro\Models\AroItem;
use App\Domain\Aro\Models\AroModifier;
use InvalidArgumentException;

/**
 * The only place the engine meets the database. Loads the head and its
 * modifiers for the requested ARO version, runs the pure engine, and applies
 * the schedule-level adjustments (posture, certificates, cost basis).
 */
final class FeeCalculator
{
    public function __construct(
        private readonly Resolver $resolver,
        private readonly ModifierPipeline $pipeline,
    ) {}

    public function minimum(FeeRequest $r): Computed
    {
        $item = AroItem::query()
            ->whereBelongsTo($r->version, 'version')
            ->where('code', $r->itemCode)
            ->firstOrFail();

        $this->assertChargeable($item);

        $base = $this->resolver
            ->computationFor($item, $r->scale, $r->agreedRate, $r->instructionFee)
            ->compute($r->basis, $r->quantity);

        $c = $this->pipeline->apply($base, $this->modifiers($item, $r));

        $table = $this->postureTable($item, $r->scale);
        if ($table === null && $r->posture !== null) {
            throw new InvalidArgumentException("{$item->code} does not take a posture — only Sch 6/7 item 1 instruction fees do");
        }
        if ($table !== null) {
            $c = PostureMultiplier::apply($c, $r->posture ?? $table->defaultPosture(), $table);
        }

        $certificates = $r->certificates ?? new Certificates;
        if ($certificates->any()) {
            if ($item->schedule !== 6 || ! $item->is_instruction_fee) {
                throw new InvalidArgumentException("Certificates under Sch 6 provisos (ii)/(iii) apply to Schedule 6 instruction fees only, not {$item->code}");
            }
            $c = $certificates->applyToInstruction($c);
        }

        if ($this->upliftsForAdvocateClient($item, $r) && $r->costBasis === CostBasis::AdvocateClient) {
            $c = CostBasisUplift::advocateClient($c, "Sch {$item->schedule}");
        }

        return $c;
    }

    private function assertChargeable(AroItem $item): void
    {
        if (! $item->is_active) {
            throw new InvalidArgumentException("{$item->code} is not chargeable: ".(string) ($item->params['pending'] ?? 'inactive in this ARO version'));
        }

        if ($item->computation === 'pointer') {
            throw new InvalidArgumentException(sprintf(
                '%s is not a chargeable head — it is charged under %s; bill that head instead',
                $item->code,
                (string) ($item->params['target'] ?? 'Schedule 5'),
            ));
        }
    }

    /** @return Modifier[] */
    private function modifiers(AroItem $item, FeeRequest $r): array
    {
        if ($r->modifierCodes === []) {
            return [];
        }

        $rows = AroModifier::query()
            ->whereBelongsTo($r->version, 'version')
            ->whereIn('code', $r->modifierCodes)
            ->get();

        $missing = array_diff($r->modifierCodes, $rows->pluck('code')->all());
        if ($missing !== []) {
            throw new InvalidArgumentException('Unknown modifier code(s) for this ARO version: '.implode(', ', $missing));
        }

        return $rows->map(function (AroModifier $m) use ($item, $r) {
            if ($m->applies_to_codes !== null && ! in_array($item->code, $m->applies_to_codes, true)) {
                throw new InvalidArgumentException("Modifier {$m->code} does not apply to {$item->code}");
            }

            $engine = $m->toEngine($r->modifierAmounts[$m->code] ?? null);

            if ($engine->op === ModifierOp::Add && $engine->money()->isZero()) {
                throw new InvalidArgumentException("Modifier {$m->code} requires an amount — pass modifierAmounts['{$m->code}']");
            }

            return $engine;
        })->all();
    }

    /**
     * Sch 6/7 item 1(a)–(c) attach to the value tables only. The table is
     * named on the item (`posture_table: a|b`) or, for a lower/higher item,
     * per scale (`posture_table: {lower: a, higher: b}`).
     */
    private function postureTable(AroItem $item, ?string $scale): ?PostureTable
    {
        $spec = $item->params['posture_table'] ?? null;

        if (is_array($spec)) {
            $spec = $scale === null ? null : ($spec[$scale] ?? null);
        }

        return is_string($spec) ? PostureTable::from($spec) : null;
    }

    /**
     * Part B ("advocate and client costs") of Sch 6, 7, 8, 9 and 11 uplifts
     * every Part A figure. Sch 10 Part B applies "in contested matter" only,
     * so its neutral heads carry `contested_only` and take the matter flag.
     */
    private function upliftsForAdvocateClient(AroItem $item, FeeRequest $r): bool
    {
        return match ($item->applies_cost_basis) {
            'contentious' => true,
            'contested_only' => $r->contested,
            default => false,
        };
    }
}
