<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

use Brick\Money\Money;

final readonly class Step
{
    /**
     * @param  Money  $amount  contribution of this step (a delta; negative when a replace lowers the total)
     * @param  bool  $replaces  true when the step set the running total rather than adding to it
     */
    public function __construct(
        public string $ruleRef,
        public string $description,
        public Money $amount,
        public Money $runningTotal,
        public bool $replaces = false,
    ) {}
}
