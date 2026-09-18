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
        public readonly FeeBound $bound = FeeBound::Prescribed,
        public readonly ?Money $ceiling = null,
    ) {}

    public static function zero(): self
    {
        return new self(Money::zero('KES'), []);
    }

    public function add(string $ruleRef, string $description, Money $delta): self
    {
        $total = $this->amount->plus($delta);

        return new self($total, [...$this->steps, new Step($ruleRef, $description, $delta, $total)], $this->bound, $this->ceiling);
    }

    public function replace(string $ruleRef, string $description, Money $new): self
    {
        return new self(
            $new,
            [...$this->steps, new Step($ruleRef, $description, $new->minus($this->amount), $new, replaces: true)],
            $this->bound,
            $this->ceiling,
        );
    }

    /** "Not less than": the amount is a floor the advocate may exceed with justification. */
    public function markDiscretionary(): self
    {
        return $this->withBound(FeeBound::Minimum);
    }

    /** "Not exceeding": the amount is a ceiling; anything up to it may be charged. */
    public function markMaximum(): self
    {
        return $this->withBound(FeeBound::Maximum);
    }

    public function withBound(FeeBound $bound): self
    {
        return new self($this->amount, $this->steps, $bound, $this->ceiling);
    }

    /** A floor head that also carries an upper limit ("not less than 20,000 … not to exceed 50,000"). */
    public function withCeiling(Money $ceiling): self
    {
        return new self($this->amount, $this->steps, $this->bound, $ceiling);
    }

    public function isDiscretionary(): bool
    {
        return $this->bound === FeeBound::Minimum;
    }

    public function isMaximum(): bool
    {
        return $this->bound === FeeBound::Maximum;
    }

    /**
     * Snapshot for storage on a chargeable item or bill line, and for the UI.
     *
     * @return array{amount_cents:int, bound:string, ceiling_cents:int|null, provenance:string, steps:list<array{rule_ref:string, description:string, amount_cents:int, running_total_cents:int, replaces:bool}>}
     */
    public function toArray(): array
    {
        return [
            'amount_cents' => $this->amount->getMinorAmount()->toInt(),
            'bound' => $this->bound->value,
            'ceiling_cents' => $this->ceiling?->getMinorAmount()->toInt(),
            'provenance' => $this->provenance(),
            'steps' => array_values(array_map(fn (Step $s) => [
                'rule_ref' => $s->ruleRef,
                'description' => $s->description,
                'amount_cents' => $s->amount->getMinorAmount()->toInt(),
                'running_total_cents' => $s->runningTotal->getMinorAmount()->toInt(),
                'replaces' => $s->replaces,
            ], $this->steps)),
        ];
    }

    public function provenance(): string
    {
        $text = implode('; ', array_map(
            fn (Step $s) => sprintf(
                '%s: %s = %s',
                $s->ruleRef,
                $s->description,
                ($s->replaces ? $s->runningTotal : $s->amount)->formatToLocale('en_KE', CurrencyDisplay::Code),
            ),
            $this->steps,
        ));

        if ($this->ceiling !== null) {
            $text .= sprintf('; not to exceed %s', $this->ceiling->formatToLocale('en_KE', CurrencyDisplay::Code));
        }

        return $text;
    }
}
