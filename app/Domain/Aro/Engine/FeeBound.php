<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

/**
 * How the Order binds a computed figure.
 *
 * - Prescribed: the fee is exactly this amount.
 * - Minimum: "such sum as may be reasonable but not less than X" — X is a floor.
 * - Maximum: "a reasonable amount not exceeding X" — X is a ceiling.
 */
enum FeeBound: string
{
    case Prescribed = 'prescribed';
    case Minimum = 'minimum';
    case Maximum = 'maximum';

    public function description(): string
    {
        return match ($this) {
            self::Prescribed => 'prescribed fee',
            self::Minimum => 'not less than',
            self::Maximum => 'not exceeding',
        };
    }
}
