<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine\Computations;

use App\Domain\Aro\Engine\Band;
use App\Domain\Aro\Engine\Computation;
use App\Domain\Aro\Engine\Computed;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use InvalidArgumentException;
use LogicException;

/**
 * Non-cumulative: the rate of the band containing the basis is applied to the
 * whole basis (the rejected Sch 2 Note 4 reading, kept so both readings are
 * expressible; also the Sch 10 item 7(b) commission caps).
 */
final class BracketRateComputation implements Computation
{
    /** @param Band[] $bands */
    public function __construct(
        private readonly string $ruleRef,
        private readonly array $bands,
    ) {}

    public function compute(?Money $basis, int|float|null $quantity = null): Computed
    {
        if ($basis === null || ! $basis->isPositive()) {
            throw new InvalidArgumentException("{$this->ruleRef} requires a positive subject-matter value (para 21)");
        }

        foreach ($this->bands as $band) {
            if ($band->contains($basis)) {
                if ($band->rate === null) {
                    throw new LogicException("{$this->ruleRef}: band has no rate");
                }

                $fee = $basis->multipliedBy($band->rate, RoundingMode::HalfUp);
                $c = Computed::zero()->add($this->ruleRef, sprintf('%s × whole basis', $band->rate), $fee);
                if ($band->floor !== null && $fee->isLessThan($band->floor)) {
                    $c = $c->replace($this->ruleRef.' floor', 'minimum', $band->floor);
                }

                return $c;
            }
        }

        throw new LogicException('No band contains basis');
    }
}
