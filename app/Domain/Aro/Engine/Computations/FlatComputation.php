<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine\Computations;

use App\Domain\Aro\Engine\Computation;
use App\Domain\Aro\Engine\Computed;
use Brick\Money\Money;

final class FlatComputation implements Computation
{
    public function __construct(
        private readonly string $ruleRef,
        private readonly Money $amount,
        private readonly bool $discretionary = false,
    ) {}

    public function compute(?Money $basis, int|float|null $quantity = null): Computed
    {
        $c = Computed::zero()->add($this->ruleRef, $this->discretionary ? 'not less than' : 'prescribed fee', $this->amount);

        return $this->discretionary ? $c->markDiscretionary() : $c;
    }
}
