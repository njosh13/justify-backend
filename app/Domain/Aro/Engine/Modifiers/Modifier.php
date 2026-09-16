<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine\Modifiers;

use Brick\Money\Money;
use InvalidArgumentException;

final readonly class Modifier
{
    public function __construct(
        public string $code,
        public string $ruleRef,
        public ModifierOp $op,
        public string|Money $value,
        public int $sortOrder,
    ) {}

    /** Decimal-string ratio for multiply / floor_ratio_of_base. */
    public function ratio(): string
    {
        if (! is_string($this->value)) {
            throw new InvalidArgumentException("Modifier {$this->code} expects a ratio value");
        }

        return $this->value;
    }

    /** Money amount for add / floor / cap. */
    public function money(): Money
    {
        if (! $this->value instanceof Money) {
            throw new InvalidArgumentException("Modifier {$this->code} expects a money value");
        }

        return $this->value;
    }
}
