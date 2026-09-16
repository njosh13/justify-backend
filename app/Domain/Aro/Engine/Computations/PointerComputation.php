<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine\Computations;

use App\Domain\Aro\Engine\Computation;
use App\Domain\Aro\Engine\Computed;
use Brick\Money\Money;

/**
 * Catalogue entries that charge under another head — e.g. Sch 1 Note 2
 * (debenture without security → Schedule 5) or Sch 2 Note 2 (extension by
 * endorsement → Schedule 5). Produces a zero-amount step so the pointer is
 * visible in provenance; the caller bills the referenced item separately.
 */
final class PointerComputation implements Computation
{
    public function __construct(
        private readonly string $ruleRef,
        private readonly string $target,
    ) {}

    public function compute(?Money $basis, int|float|null $quantity = null): Computed
    {
        return Computed::zero()->add($this->ruleRef, "charged under {$this->target}", Money::zero('KES'));
    }
}
