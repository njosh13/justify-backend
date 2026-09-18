<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine\Computations;

use App\Domain\Aro\Engine\Computation;
use App\Domain\Aro\Engine\Computed;
use App\Domain\Aro\Engine\GettingUpFee;
use Brick\Money\Money;

/**
 * Sch 6 para 2 / Sch 8 para 2: derived from the instruction fee allowed, so
 * the head has no figure of its own in the catalogue.
 */
final class GettingUpComputation implements Computation
{
    public function __construct(
        private readonly string $ruleRef,
        private readonly Money $instructionFee,
    ) {}

    public function compute(?Money $basis, int|float|null $quantity = null): Computed
    {
        $instruction = Computed::zero()->add($this->ruleRef, 'instruction fee allowed', $this->instructionFee);

        return GettingUpFee::minimum($instruction, $this->ruleRef);
    }
}
