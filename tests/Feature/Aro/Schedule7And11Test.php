<?php

declare(strict_types=1);

use App\Domain\Aro\Engine\CostBasis;
use App\Domain\Aro\Engine\Posture;

beforeEach(fn () => seedAro());

describe('Sch 7 — S7.INSTR lower/higher', function () {
    it('40,000 → lower 10,000 / higher 15,000', function () {
        expect(fee('S7.INSTR', 40_000, scale: 'lower'))->toBe(10_000.0)
            ->and(fee('S7.INSTR', 40_000, scale: 'higher'))->toBe(15_000.0);
    });
    it('150,000 → lower 30,000 / higher 40,000', function () {
        expect(fee('S7.INSTR', 150_000, scale: 'lower'))->toBe(30_000.0)
            ->and(fee('S7.INSTR', 150_000, scale: 'higher'))->toBe(40_000.0);
    });
    it('1,500,000 → lower 90,000 / higher 120,000', function () {
        expect(fee('S7.INSTR', 1_500_000, scale: 'lower'))->toBe(90_000.0)
            ->and(fee('S7.INSTR', 1_500_000, scale: 'higher'))->toBe(120_000.0);
    });
    it('3,000,000 → lower 115,000 / higher 145,000', function () {
        expect(fee('S7.INSTR', 3_000_000, scale: 'lower'))->toBe(115_000.0)
            ->and(fee('S7.INSTR', 3_000_000, scale: 'higher'))->toBe(145_000.0);
    });
    it('3,000,000 higher, advocate-and-client → 217,500', function () {
        expect(fee('S7.INSTR', 3_000_000, scale: 'higher', costBasis: CostBasis::AdvocateClient))->toBe(217_500.0);
    });
    it('first hearing day 5,000 + 2 subsequent parts 4,200', function () {
        expect(fee('S7.HEARING.FIRST_DAY') + fee('S7.HEARING.SUBSEQUENT_PART', quantity: 2))->toBe(9_200.0);
    });
});

describe('Sch 11 — S11.INSTR', function () {
    it('30,000 → higher 17,640 / lower 8,820', function () {
        expect(fee('S11.INSTR', 30_000, scale: 'higher'))->toBe(17_640.0)
            ->and(fee('S11.INSTR', 30_000, scale: 'lower'))->toBe(8_820.0);
    });
    it('5,000,000 → 140,000', fn () => expect(fee('S11.INSTR', 5_000_000, scale: 'higher'))->toBe(140_000.0));
    it('100,000,000 → 690,000', fn () => expect(fee('S11.INSTR', 100_000_000, scale: 'higher'))->toBe(690_000.0));
    it('300,000,000 → 1,490,000', fn () => expect(fee('S11.INSTR', 300_000_000, scale: 'higher'))->toBe(1_490_000.0));
    it('100,000,000 advocate-and-client → 1,035,000', function () {
        expect(fee('S11.INSTR', 100_000_000, scale: 'higher', costBasis: CostBasis::AdvocateClient))->toBe(1_035_000.0);
    });
});

describe('Sch 7 posture follows the scale (I-11)', function () {
    it('applies 65% no-appearance on the lower scale', fn () => expect(fee('S7.INSTR', 150_000, scale: 'lower', posture: Posture::NoAppearance))->toBe(19_500.0));
    it('applies 75% summary on the higher scale', fn () => expect(fee('S7.INSTR', 150_000, scale: 'higher', posture: Posture::Summary))->toBe(30_000.0));

    it('rejects summary on the lower scale', function () {
        fee('S7.INSTR', 150_000, scale: 'lower', posture: Posture::Summary);
    })->throws(InvalidArgumentException::class, 'does not apply to the undefended table');

    it('carries the 50,000 ceiling on the no-sum undefended head', function () {
        $c = computed('S7.INSTR.NO_SUM.UNDEFENDED');

        expect($c->amount->getAmount()->toFloat())->toBe(20_000.0)
            ->and($c->isDiscretionary())->toBeTrue()
            ->and($c->ceiling?->getAmount()->toFloat())->toBe(50_000.0)
            ->and($c->provenance())->toContain("not to exceed KES\u{A0}50,000.00");
    });
});
