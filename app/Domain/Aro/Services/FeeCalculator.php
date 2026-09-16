<?php

declare(strict_types=1);

namespace App\Domain\Aro\Services;

use App\Domain\Aro\Engine\Certificates;
use App\Domain\Aro\Engine\Computed;
use App\Domain\Aro\Engine\CostBasis;
use App\Domain\Aro\Engine\CostBasisUplift;
use App\Domain\Aro\Engine\FeeRequest;
use App\Domain\Aro\Engine\Modifiers\ModifierOp;
use App\Domain\Aro\Engine\Modifiers\ModifierPipeline;
use App\Domain\Aro\Engine\Posture;
use App\Domain\Aro\Engine\PostureMultiplier;
use App\Domain\Aro\Engine\Resolver;
use App\Domain\Aro\Models\AroItem;
use App\Domain\Aro\Models\AroModifier;

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

        $base = $this->resolver->computationFor($item, $r->scale)->compute($r->basis, $r->quantity);

        $modifiers = AroModifier::query()
            ->whereBelongsTo($r->version, 'version')
            ->whereIn('code', $r->modifierCodes)
            ->get()
            ->map(function (AroModifier $m) use ($item, $r) {
                if ($m->applies_to_codes !== null && ! in_array($item->code, $m->applies_to_codes, true)) {
                    throw new \InvalidArgumentException("Modifier {$m->code} does not apply to {$item->code}");
                }

                $engine = $m->toEngine($r->modifierAmounts[$m->code] ?? null);

                if ($engine->op === ModifierOp::Add && $engine->money()->isZero()) {
                    throw new \InvalidArgumentException("Modifier {$m->code} requires an amount — pass modifierAmounts['{$m->code}']");
                }

                return $engine;
            })
            ->all();

        $c = $this->pipeline->apply($base, $modifiers);

        if ($item->is_instruction_fee) {
            $c = PostureMultiplier::apply($c, $r->posture ?? Posture::FullTrial);
            $c = ($r->certificates ?? new Certificates)->applyToInstruction($c);
        }

        if ($item->applies_cost_basis === 'contentious' && $r->costBasis === CostBasis::AdvocateClient) {
            $c = CostBasisUplift::advocateClient($c, "Sch {$item->schedule}");
        }

        return $c;
    }
}
