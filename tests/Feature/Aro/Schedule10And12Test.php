<?php

declare(strict_types=1);

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
