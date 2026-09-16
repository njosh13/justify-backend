<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

use Brick\Money\CurrencyDisplay;
use Brick\Money\Money;

final class Computed
{
    /** @param Step[] $steps */
    private function __construct(
        public readonly Money $amount,
        public readonly array $steps,
        public readonly bool $discretionary = false,
    ) {}

    public static function zero(): self
    {
        return new self(Money::zero('KES'), []);
    }

    public function add(string $ruleRef, string $description, Money $delta): self
    {
        $total = $this->amount->plus($delta);

        return new self($total, [...$this->steps, new Step($ruleRef, $description, $delta, $total)], $this->discretionary);
    }

    public function replace(string $ruleRef, string $description, Money $new): self
    {
        return $this->add($ruleRef, $description, $new->minus($this->amount));
    }

    public function markDiscretionary(): self
    {
        return new self($this->amount, $this->steps, true);
    }

    public function provenance(): string
    {
        return implode('; ', array_map(
            fn (Step $s) => sprintf('%s: %s = %s', $s->ruleRef, $s->description, $s->amount->formatToLocale('en_KE', CurrencyDisplay::Code)),
            $this->steps,
        ));
    }
}
