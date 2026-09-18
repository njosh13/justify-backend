<?php

declare(strict_types=1);

use App\Domain\Aro\Engine\CostBasis;

beforeEach(fn () => seedAro());

describe('Sch 8 — Landlord & Tenant tribunal', function () {
    it('30,000 → lower 8,000 / higher 15,000', function () {
        expect(fee('S8.INSTR', 30_000, scale: 'lower'))->toBe(8_000.0)
            ->and(fee('S8.INSTR', 30_000, scale: 'higher'))->toBe(15_000.0);
    });
    it('over 250,000 adds 2% to the fee for 250,000', fn () => expect(fee('S8.INSTR', 350_000, scale: 'higher'))->toBe(37_000.0));
    it('halves the instruction fee for leave to levy distress', fn () => expect(fee('S8.INSTR', 30_000, scale: 'higher', modifiers: ['S8.DISTRESS']))->toBe(7_500.0));
    it('uplifts 50% advocate-and-client', fn () => expect(fee('S8.INSTR', 30_000, scale: 'higher', costBasis: CostBasis::AdvocateClient))->toBe(22_500.0));
    it('derives getting-up from the instruction fee', fn () => expect(fee('S8.GETTING_UP', instructionFee: 15_000))->toBe(5_000.0));
    it('treats the per-km service allowance as a ceiling', fn () => expect(computed('S8.SERVICE.PER_KM', quantity: 4)->isMaximum())->toBeTrue());
});

describe('Sch 9 — Rent Restriction tribunal', function () {
    it('4,000 → lower 3,528 / higher 8,232', function () {
        expect(fee('S9.INSTR', 4_000, scale: 'lower'))->toBe(3_528.0)
            ->and(fee('S9.INSTR', 4_000, scale: 'higher'))->toBe(8_232.0);
    });
    it('over 50,000 adds 2% to the fee for 50,000', fn () => expect(fee('S9.INSTR', 100_000, scale: 'higher'))->toBe(18_640.0));

    it('treats opposed non-pecuniary relief as a 25,000 ceiling, not a floor', function () {
        $c = computed('S9.NON_PECUNIARY.OPPOSED');

        expect($c->amount->getAmount()->toFloat())->toBe(25_000.0)
            ->and($c->isMaximum())->toBeTrue()
            ->and($c->isDiscretionary())->toBeFalse();
    });

    it('keeps unopposed non-pecuniary relief as a 15,000 floor', fn () => expect(computed('S9.NON_PECUNIARY.UNOPPOSED')->isDiscretionary())->toBeTrue());
});
