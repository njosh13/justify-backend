<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine\Computations;

use App\Domain\Aro\Engine\Computation;
use App\Domain\Aro\Engine\Computed;
use App\Domain\Aro\Engine\FeeBound;
use Brick\Money\Money;

/**
 * Decorates any computation with the Order's binding language: "not less than
 * twice the fee" (Sch 10 item 1(d)), "not exceeding … per annum" (Sch 10 item
 * 7(b)), or a floor with a stated ceiling (Sch 7 item 2).
 */
final class BoundedComputation implements Computation
{
    public function __construct(
        private readonly Computation $inner,
        private readonly ?FeeBound $bound = null,
        private readonly ?Money $ceiling = null,
    ) {}

    public function compute(?Money $basis, int|float|null $quantity = null): Computed
    {
        $c = $this->inner->compute($basis, $quantity);

        if ($this->bound !== null) {
            $c = $c->withBound($this->bound);
        }

        if ($this->ceiling !== null) {
            $c = $c->withCeiling($this->ceiling);
        }

        return $c;
    }
}
