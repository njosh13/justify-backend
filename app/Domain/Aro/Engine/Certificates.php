<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

use Brick\Math\RoundingMode;

final class Certificates
{
    public function __construct(
        public bool $twoAdvocates = false,
        public bool $seniorCounsel = false,
        public bool $higherScaleOrder = false,
    ) {}

    public function applyToInstruction(Computed $c): Computed
    {
        if ($this->twoAdvocates) {
            $c = $c->replace('Sch 6 proviso (ii)', 'certificate for two advocates — doubled', $c->amount->multipliedBy(2));
        }
        if ($this->seniorCounsel) {
            $c = $c->replace('Sch 6 proviso (iii)', 'certificate for senior counsel — increased by one-half', $c->amount->multipliedBy('1.5', RoundingMode::HalfUp));
        }

        return $c;
    }
}
