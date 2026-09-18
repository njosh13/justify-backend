<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine\Computations;

use App\Domain\Aro\Engine\Computation;
use App\Domain\Aro\Engine\Computed;
use App\Domain\Aro\Engine\FeeBound;
use Brick\Money\Money;
use InvalidArgumentException;

/**
 * A fixed figure from the Order. `$quantity` (default 1) multiplies it —
 * "for every day of not less than seven hours … 15,000", three patents at
 * 42,000 each — and must be a whole positive number.
 */
final class FlatComputation implements Computation
{
    public function __construct(
        private readonly string $ruleRef,
        private readonly Money $amount,
        private readonly FeeBound $bound = FeeBound::Prescribed,
    ) {}

    public function compute(?Money $basis, int|float|null $quantity = null): Computed
    {
        $units = self::wholeUnits($this->ruleRef, $quantity ?? 1);

        $c = Computed::zero()->add(
            $this->ruleRef,
            $units === 1 ? $this->bound->description() : sprintf('%s × %d', $this->bound->description(), $units),
            $this->amount->multipliedBy($units),
        );

        return $c->withBound($this->bound);
    }

    public static function wholeUnits(string $ruleRef, int|float $quantity): int
    {
        if ($quantity < 1 || (float) $quantity !== floor((float) $quantity)) {
            throw new InvalidArgumentException("{$ruleRef} takes a whole quantity of at least one, got {$quantity}");
        }

        return (int) $quantity;
    }
}
