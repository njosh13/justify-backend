<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

use Brick\Money\CurrencyDisplay;
use Brick\Money\Money;

final readonly class Band
{
    /** @param numeric-string|null $rate */
    public function __construct(
        public Money $lower,
        public ?Money $upper,
        public ?Money $fixed,
        public ?string $rate,
        public ?Money $floor,
    ) {}

    public function contains(Money $basis): bool
    {
        return $basis->isGreaterThan($this->lower)
            && ($this->upper === null || $basis->isLessThanOrEqualTo($this->upper));
    }

    public function range(): string
    {
        return $this->upper === null
            ? 'over '.$this->lower->formatToLocale('en_KE', CurrencyDisplay::Code)
            : $this->lower->formatToLocale('en_KE', CurrencyDisplay::Code).' – '.$this->upper->formatToLocale('en_KE', CurrencyDisplay::Code);
    }
}
