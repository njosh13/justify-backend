<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

use App\Domain\Aro\Engine\Computations\BasePlusRateComputation;
use App\Domain\Aro\Engine\Computations\BracketRateComputation;
use App\Domain\Aro\Engine\Computations\FlatComputation;
use App\Domain\Aro\Engine\Computations\PerUnitBandsComputation;
use App\Domain\Aro\Engine\Computations\PerUnitComputation;
use App\Domain\Aro\Engine\Computations\PointerComputation;
use App\Domain\Aro\Engine\Computations\TieredComputation;
use App\Domain\Aro\Models\AroBand;
use App\Domain\Aro\Models\AroItem;
use Brick\Money\Money;

final class Resolver
{
    public function computationFor(AroItem $item, ?string $scale): Computation
    {
        $rows = $item->bands->sortBy('sort');
        if ($item->scale_variant === 'lower_higher') {
            $rows = $rows->where('scale', $scale ?? 'lower');
        }

        $bands = $rows->map(fn (AroBand $b) => new Band(
            Money::ofMinor($b->lower_cents, 'KES'),
            $b->upper_cents === null ? null : Money::ofMinor($b->upper_cents, 'KES'),
            $b->fixed_cents === null ? null : Money::ofMinor($b->fixed_cents, 'KES'),
            $b->rate,
            $b->floor_cents === null ? null : Money::ofMinor($b->floor_cents, 'KES'),
        ))->values()->all();

        $params = $item->params ?? [];

        return match ($item->computation) {
            'tiered' => new TieredComputation($item->rule_reference, $bands),
            'bracket_rate' => new BracketRateComputation($item->rule_reference, $bands),
            'flat' => new FlatComputation($item->rule_reference, Money::ofMinor((int) ($item->included_amount_cents ?? 0), 'KES')),
            'discretionary_floor' => new FlatComputation($item->rule_reference, Money::ofMinor((int) ($item->included_amount_cents ?? 0), 'KES'), discretionary: true),
            'per_unit', 'per_folio' => new PerUnitComputation(
                $item->rule_reference,
                $item->units_included ?? 0,
                Money::ofMinor($item->included_amount_cents ?? 0, 'KES'),
                Money::ofMinor((int) ($params['per_unit_cents'] ?? 0), 'KES'),
            ),
            'base_plus_rate' => new BasePlusRateComputation($item->rule_reference, $bands),
            'pointer' => new PointerComputation($item->rule_reference, (string) ($params['target'] ?? 'Schedule 5')),
            'per_unit_bands' => new PerUnitBandsComputation(
                $item->rule_reference,
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
            default => throw new \LogicException("Unknown computation '{$item->computation}' on {$item->code}"),
        };
    }
}
