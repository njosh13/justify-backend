<?php

declare(strict_types=1);

use App\Domain\Aro\Engine\FeeRequest;
use App\Domain\Aro\Engine\Modifiers\ModifierPipeline;
use App\Domain\Aro\Engine\Resolver;
use App\Domain\Aro\Models\AroItem;
use App\Domain\Aro\Services\FeeCalculator;
use Brick\Money\Money;

beforeEach(fn () => seedAro());

it('gives tiered/bracket items contiguous bands from 0 with one open band', function () {
    $items = AroItem::whereIn('computation', ['tiered', 'bracket_rate', 'base_plus_rate'])->with('bands')->get();

    expect($items)->not->toBeEmpty();

    foreach ($items as $item) {
        $scales = $item->scale_variant === 'lower_higher' ? ['lower', 'higher'] : [null];

        foreach ($scales as $scale) {
            $bands = $item->bands->where('scale', $scale)->sortBy('sort')->values();
            expect($bands, "{$item->code} {$scale}")->not->toBeEmpty();
            expect($bands->first()->lower_cents)->toBe(0);
            expect($bands->whereNull('upper_cents'))->toHaveCount(1);
            expect($bands->last()->upper_cents)->toBeNull();

            for ($i = 1; $i < $bands->count(); $i++) {
                expect($bands[$i]->lower_cents)->toBe($bands[$i - 1]->upper_cents);
            }
        }
    }
});

it('seeds the interpretations register', function () {
    expect(aroVersion()->interpretations()->count())->toBeGreaterThanOrEqual(10);
});

it('produces a provenance string on every computed fee', function () {
    $calc = new FeeCalculator(new Resolver, new ModifierPipeline);
    $c = $calc->minimum(new FeeRequest(version: aroVersion(), itemCode: 'S1.SALE', basis: Money::of(10_000_000, 'KES')));

    expect($c->steps)->not->toBeEmpty()
        ->and($c->provenance())->toContain('Sch 1');
});

it('keeps tiered fees monotonic non-decreasing in the basis', function () {
    $items = AroItem::where('computation', 'tiered')->where('basis_type', '!=', 'none')->with('bands')->get();
    $resolver = new Resolver;

    foreach ($items as $item) {
        $scales = $item->scale_variant === 'lower_higher' ? ['lower', 'higher'] : [null];
        foreach ($scales as $scale) {
            $computation = $resolver->computationFor($item, $scale);
            $prev = null;
            foreach ([1_000, 50_000, 100_000, 500_000, 1_000_000, 5_000_000, 25_000_000, 100_000_000, 500_000_000] as $basis) {
                $amount = $computation->compute(Money::of($basis, 'KES'))->amount;
                if ($prev !== null) {
                    expect($amount->isGreaterThanOrEqualTo($prev))->toBeTrue("{$item->code} {$scale} decreased at {$basis}");
                }
                $prev = $amount;
            }
        }
    }
});

it('resolves every active head to a computation', function () {
    $resolver = new Resolver;
    $items = AroItem::where('is_active', true)->with('bands')->get();

    expect($items)->not->toBeEmpty();

    foreach ($items as $item) {
        $scales = $item->scale_variant === 'lower_higher' ? ['lower', 'higher'] : [null];
        foreach ($scales as $scale) {
            $resolver->computationFor($item, $scale, Money::of(1, 'KES'), Money::of(1, 'KES'));
        }
    }
});

it('uses only known cost-basis and bound vocabularies', function () {
    expect(AroItem::query()->distinct()->pluck('applies_cost_basis')->all())
        ->each->toBeIn(['non_contentious', 'contentious', 'contested_only']);

    $bounds = AroItem::query()->get()->map(fn (AroItem $i) => $i->params['bound'] ?? null)->filter()->unique()->values()->all();
    expect($bounds)->each->toBeIn(['min', 'max']);
});
