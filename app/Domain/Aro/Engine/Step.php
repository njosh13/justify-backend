<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

use Brick\Money\Money;

final readonly class Step
{
    public function __construct(
        public string $ruleRef,
        public string $description,
        public Money $amount,
        public Money $runningTotal,
    ) {}
}
