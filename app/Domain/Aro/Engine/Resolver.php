<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

use App\Domain\Aro\Engine\Computations\AgreedRateComputation;
use App\Domain\Aro\Engine\Computations\BasePlusRateComputation;
use App\Domain\Aro\Engine\Computations\BoundedComputation;
use App\Domain\Aro\Engine\Computations\BracketRateComputation;
use App\Domain\Aro\Engine\Computations\FlatComputation;
use App\Domain\Aro\Engine\Computations\GettingUpComputation;
use App\Domain\Aro\Engine\Computations\PerUnitBandsComputation;
use App\Domain\Aro\Engine\Computations\PerUnitComputation;
use App\Domain\Aro\Engine\Computations\PointerComputation;
use App\Domain\Aro\Engine\Computations\TieredComputation;
use App\Domain\Aro\Models\AroBand;
use App\Domain\Aro\Models\AroItem;
use Brick\Money\Money;
use InvalidArgumentException;
use LogicException;

/**
 * Turns an `aro_items` row into a Computation. Per-request inputs that the
 * catalogue cannot hold (an agreed hourly rate, the instruction fee a
 * getting-up fee derives from) are passed in explicitly.
 */
final class Resolver
{
    public function computationFor(
        AroItem $item,
        ?string $scale,
        ?Money $agreedRate = null,
        ?Money $instructionFee = null,
    ): Computation {
        $params = $item->params ?? [];
        $computation = $this->base($item, $scale, $params, $agreedRate, $instructionFee);

        $bound = isset($params['bound']) ? self::bound((string) $params['bound'], $item->code) : null;
        $ceiling = isset($params['ceiling_cents']) ? Money::ofMinor((int) $params['ceiling_cents'], 'KES') : null;

        if ($bound === null && $ceiling === null) {
            return $computation;
        }

        return new BoundedComputation($computation, $bound, $ceiling);
    }

    /** @param array<string,mixed> $params */
    private function base(AroItem $item, ?string $scale, array $params, ?Money $agreedRate, ?Money $instructionFee): Computation
    {
        $ref = $item->rule_reference;

        return match ($item->computation) {
            'tiered' => new TieredComputation($ref, $this->bands($item, $scale)),
            'bracket_rate' => new BracketRateComputation($ref, $this->bands($item, $scale)),
            'base_plus_rate' => new BasePlusRateComputation($ref, $this->bands($item, $scale)),
            'flat' => new FlatComputation($ref, self::included($item)),
            'discretionary_floor' => new FlatComputation($ref, self::included($item), FeeBound::Minimum),
            'discretionary_cap' => new FlatComputation($ref, self::included($item), FeeBound::Maximum),
            'per_unit', 'per_folio' => new PerUnitComputation(
                $ref,
                $item->units_included ?? 0,
                self::included($item),
                Money::ofMinor((int) ($params['per_unit_cents'] ?? 0), 'KES'),
            ),
            'per_unit_bands' => new PerUnitBandsComputation(
                $ref,
                (int) $params['unit_size_cents'],
                (int) ($params['half_unit_threshold_cents'] ?? intdiv((int) $params['unit_size_cents'], 2)),
                array_map(
                    fn (array $b) => [
                        'upper_units' => $b['upper_units'] ?? null,
                        'per_unit' => Money::ofMinor((int) $b['per_unit_cents'], 'KES'),
                    ],
                    is_array($params['unit_bands'] ?? null) ? $params['unit_bands'] : [],
                ),
                isset($params['floor_cents']) ? Money::ofMinor((int) $params['floor_cents'], 'KES') : null,
            ),
            'agreed_rate' => new AgreedRateComputation(
                $ref,
                $agreedRate ?? throw new InvalidArgumentException("{$item->code} is charged at the agreed hourly rate — pass agreedRate from the fee agreement"),
            ),
            'getting_up' => new GettingUpComputation(
                $ref,
                $instructionFee ?? throw new InvalidArgumentException("{$item->code} is one-third of the instruction fee — pass instructionFee"),
            ),
            'pointer' => new PointerComputation($ref, (string) ($params['target'] ?? 'Schedule 5')),
            default => throw new LogicException("Unknown computation '{$item->computation}' on {$item->code}"),
        };
    }

    /** @return Band[] */
    private function bands(AroItem $item, ?string $scale): array
    {
        $rows = $item->bands->sortBy('sort');
        if ($item->scale_variant === 'lower_higher') {
            if (! in_array($scale, ['lower', 'higher'], true)) {
                throw new InvalidArgumentException("{$item->code} has a lower/higher scale — pass scale 'lower' or 'higher' explicitly");
            }
            $rows = $rows->where('scale', $scale);
        }

        return $rows->map(fn (AroBand $b) => new Band(
            Money::ofMinor($b->lower_cents, 'KES'),
            $b->upper_cents === null ? null : Money::ofMinor($b->upper_cents, 'KES'),
            $b->fixed_cents === null ? null : Money::ofMinor($b->fixed_cents, 'KES'),
            $b->rate,
            $b->floor_cents === null ? null : Money::ofMinor($b->floor_cents, 'KES'),
        ))->values()->all();
    }

    private static function included(AroItem $item): Money
    {
        return Money::ofMinor((int) ($item->included_amount_cents ?? 0), 'KES');
    }

    private static function bound(string $value, string $code): FeeBound
    {
        return match ($value) {
            'min', 'minimum' => FeeBound::Minimum,
            'max', 'maximum' => FeeBound::Maximum,
            default => throw new LogicException("Unknown bound '{$value}' on {$code} — use min or max"),
        };
    }
}
