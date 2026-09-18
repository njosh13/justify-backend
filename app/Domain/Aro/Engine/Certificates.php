<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

use Brick\Math\RoundingMode;

/**
 * Sch 6 provisos (ii) and (iii): certificates granted by the judge under
 * paras 59 and 60. They attach to Schedule 6 instruction fees only.
 */
final class Certificates
{
    public function __construct(
        public bool $twoAdvocates = false,
        public bool $seniorCounsel = false,
        public bool $higherScaleOrder = false,
    ) {}

    public function any(): bool
    {
        return $this->twoAdvocates || $this->seniorCounsel || $this->higherScaleOrder;
    }

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
