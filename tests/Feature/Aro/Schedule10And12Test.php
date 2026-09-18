<?php

declare(strict_types=1);

use App\Domain\Aro\Engine\CostBasis;

beforeEach(fn () => seedAro());

describe('Sch 10 — probate (I-9: bands ≤1M provisional)', function () {
    it('uncontested grant, gross estate 3,000,000 → 70,000', fn () => expect(fee('S10.GRANT.UNCONTESTED', 3_000_000))->toBe(70_000.0));
    it('uncontested grant, 10,000,000 → 140,000', fn () => expect(fee('S10.GRANT.UNCONTESTED', 10_000_000))->toBe(140_000.0));
    it('contested grant, 3,000,000 → ≥140,000 (twice)', fn () => expect(fee('S10.GRANT.CONTESTED', 3_000_000))->toBe(140_000.0));
    it('contested re-sealing, 3,000,000 → 56,000 (four-fifths)', fn () => expect(fee('S10.RESEAL.CONTESTED', 3_000_000))->toBe(56_000.0));
    it('inventory floor at 3,000', fn () => expect(fee('S10.INVENTORY', 20_000, quantity: 1))->toBe(3_000.0));

    it('inventory: literal 2,103 per 20,000 × entries', function () {
        // I-10: literal reading → 20 units × 5 entries × 2,103 = 210,300.
        // Plan worked case expects 52,575 — pending reconciliation.
        expect(fee('S10.INVENTORY', 400_000, quantity: 5))->toBe(210_300.0);
    })->skip('I-10 unreconciled — literal formula vs plan expected value');
});

describe('Sch 12 — patents', function () {
    it('three patents at 42,000 each', function () {
        expect(fee('S12.PATENT') * 3)->toBe(126_000.0);
    });
});

describe('Sch 10 Part B applies in contested matters only (I-12)', function () {
    it('does not uplift an uncontested grant advocate-and-client', fn () => expect(fee('S10.GRANT.UNCONTESTED', 3_000_000, costBasis: CostBasis::AdvocateClient))->toBe(70_000.0));
    it('does not uplift an uncontested confirmation advocate-and-client', fn () => expect(fee('S10.CONFIRMATION.UNCONTESTED', costBasis: CostBasis::AdvocateClient))->toBe(15_000.0));
    it('uplifts a contested grant advocate-and-client', fn () => expect(fee('S10.GRANT.CONTESTED', 3_000_000, costBasis: CostBasis::AdvocateClient))->toBe(210_000.0));
    it('marks the contested grant as a floor', fn () => expect(computed('S10.GRANT.CONTESTED', 3_000_000)->isDiscretionary())->toBeTrue());
    it('leaves a letter alone when the matter is not contested', fn () => expect(fee('S10.LETTER', costBasis: CostBasis::AdvocateClient))->toBe(300.0));
    it('uplifts a letter when the matter is contested', fn () => expect(fee('S10.LETTER', costBasis: CostBasis::AdvocateClient, contested: true))->toBe(450.0));
    it('never uplifts party-and-party', fn () => expect(fee('S10.LETTER', contested: true))->toBe(300.0));
});

describe('Sch 10 item 7(b) commission caps', function () {
    it('reports 2.5% on net capital as a ceiling', function () {
        $c = computed('S10.ADMIN.COMMISSION.CAPITAL', 4_000_000);

        expect($c->amount->getAmount()->toFloat())->toBe(100_000.0)
            ->and($c->isMaximum())->toBeTrue();
    });
});

describe('Sch 12 quantities and inactive heads', function () {
    it('charges three patents at 42,000 each in one line', fn () => expect(fee('S12.PATENT', quantity: 3))->toBe(126_000.0));

    it('refuses the caution head whose fee is absent from the published text', function () {
        fee('S12.CAUTION');
    })->throws(InvalidArgumentException::class, 'not chargeable');
});
