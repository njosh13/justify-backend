<?php

declare(strict_types=1);
use App\Domain\Aro\Engine\FeeRequest;
use App\Domain\Aro\Engine\Modifiers\ModifierPipeline;
use App\Domain\Aro\Engine\Resolver;
use App\Domain\Aro\Services\FeeCalculator;

beforeEach(fn () => seedAro());

describe('Sch 3 — companies', function () {
    it('has a 60,000 floor for incorporation', function () {
        expect(fee('S3.INCORPORATION'))->toBe(60_000.0);
    });

    it('marks the floor discretionary', function () {
        $v = aroVersion();
        $calc = new FeeCalculator(new Resolver, new ModifierPipeline);
        $c = $calc->minimum(new FeeRequest(version: $v, itemCode: 'S3.INCORPORATION'));
        expect($c->discretionary)->toBeTrue();
    });
});

describe('Sch 5 — units, debt collection, chattels', function () {
    it('debt ≤ 100,000 at 10%', fn () => expect(fee('S5.DEBT_COLLECTION', 80_000))->toBe(8_000.0));
    it('debt 300,000 = 10,000 + 5%', fn () => expect(fee('S5.DEBT_COLLECTION', 300_000))->toBe(20_000.0));
    it('debt 1,000,000 = 50,000 + 3%', fn () => expect(fee('S5.DEBT_COLLECTION', 1_000_000))->toBe(65_000.0));
    it('debt 5,000,000 = 100,000 + 1.5%', fn () => expect(fee('S5.DEBT_COLLECTION', 5_000_000))->toBe(145_000.0));

    it('halves debt fee where only one letter of demand written', function () {
        expect(fee('S5.DEBT_COLLECTION', 80_000, modifiers: ['S5.DEBT.ONE_LETTER', 'S5.DEBT.ONE_LETTER.FLOOR']))->toBe(4_000.0);
    });

    it('floors one-letter debt fee at 1,000', function () {
        expect(fee('S5.DEBT_COLLECTION', 5_000, modifiers: ['S5.DEBT.ONE_LETTER', 'S5.DEBT.ONE_LETTER.FLOOR']))->toBe(1_000.0);
    });

    it('charges attendance per 15 minutes or part', fn () => expect(fee('S5.ATTENDANCE', quantity: 3))->toBe(3_000.0));
    it('rounds part-units up', fn () => expect(fee('S5.ATTENDANCE', quantity: 2.4))->toBe(3_000.0));
    it('charges time engaged at 7,000 per 15 minutes', fn () => expect(fee('S5.TIME_ENGAGED', quantity: 8))->toBe(56_000.0));
    it('charges journey day rate', fn () => expect(fee('S5.JOURNEY.DAY'))->toBe(15_000.0));
    it('charges journey hourly rate', fn () => expect(fee('S5.JOURNEY.HOUR', quantity: 3))->toBe(7_500.0));
    it('sets opinion floor at 35,000', fn () => expect(fee('S5.OPINION'))->toBe(35_000.0));
    it('charges chattels ≤50,000 flat', fn () => expect(fee('S5.CHATTELS.SMALL'))->toBe(6_000.0));

    it('charges chattels >50,000 at half the Sch 1 Second Scale', function () {
        expect(fee('S1.SECURITY.GRANTEE', 10_000_000, modifiers: ['S5.CHATTELS.HALF']))->toBe(71_875.0);
    });
});
