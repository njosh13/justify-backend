<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

use Brick\Money\Money;

interface Computation
{
    public function compute(?Money $basis, int|float|null $quantity = null): Computed;
}
