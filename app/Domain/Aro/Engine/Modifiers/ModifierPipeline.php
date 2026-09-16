<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine\Modifiers;

use App\Domain\Aro\Engine\Computed;
use Brick\Math\RoundingMode;

final class ModifierPipeline
{
    /** @param Modifier[] $modifiers */
    public function apply(Computed $base, array $modifiers): Computed
    {
        usort($modifiers, fn (Modifier $a, Modifier $b) => $a->sortOrder <=> $b->sortOrder);
        $original = $base->amount;
        $c = $base;

        foreach ($modifiers as $m) {
            $c = match ($m->op) {
                ModifierOp::Multiply => $c->replace($m->ruleRef, "× {$m->value}", $c->amount->multipliedBy($m->ratio(), RoundingMode::HalfUp)),
                ModifierOp::Add => $c->add($m->ruleRef, 'add', $m->money()),
                ModifierOp::Floor => $c->amount->isLessThan($m->money())
                    ? $c->replace($m->ruleRef, 'subject to minimum', $m->money()) : $c,
                ModifierOp::Cap => $c->amount->isGreaterThan($m->money())
                    ? $c->replace($m->ruleRef, 'subject to maximum', $m->money()) : $c,
                ModifierOp::FloorRatioOfBase => (function () use ($c, $m, $original) {
                    $floor = $original->multipliedBy($m->ratio(), RoundingMode::HalfUp);

                    return $c->amount->isLessThan($floor)
                        ? $c->replace($m->ruleRef, "reduction capped at {$m->value} of scale", $floor) : $c;
                })(),
            };
        }

        return $c;
    }
}
