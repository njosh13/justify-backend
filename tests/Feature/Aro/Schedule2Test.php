<?php

declare(strict_types=1);

beforeEach(fn () => seedAro());

describe('Sch 2 — S2.LEASE.PREPARE (I-2/I-3 cumulative reading)', function () {
    it('floors at 20,000', fn () => expect(fee('S2.LEASE.PREPARE', 100_000))->toBe(20_000.0));
    it('computes 15% band', fn () => expect(fee('S2.LEASE.PREPARE', 300_000))->toBe(45_000.0));
    it('tops band 1', fn () => expect(fee('S2.LEASE.PREPARE', 500_000))->toBe(75_000.0));
    it('adds 3% on the excess over 500,000', fn () => expect(fee('S2.LEASE.PREPARE', 1_000_000))->toBe(90_000.0));
    it('tops band 2', fn () => expect(fee('S2.LEASE.PREPARE', 3_000_000))->toBe(150_000.0));
    it('adds 1% over 3,000,000 (I-3 — no cliff)', fn () => expect(fee('S2.LEASE.PREPARE', 4_000_000))->toBe(160_000.0));

    it('halves for perusal by the lessee\'s advocate', fn () => expect(fee('S2.LEASE.PERUSE', 1_000_000))->toBe(45_000.0));

    it('reduces common-form leases by one-third', function () {
        expect(fee('S2.LEASE.PREPARE', 1_000_000, modifiers: ['S2.PRINTED_FORM']))->toBe(60_000.0);
    });

    it('charges S1.SALE on the premium as a separate line (para 48)', function () {
        $lease = fee('S2.LEASE.PREPARE', 1_000_000);
        $premium = fee('S1.SALE', 10_000_000);
        expect($lease + $premium)->toBe(265_000.0);
    });

    it('never produces the rejected bracket-rate figure of 30,000 on 1,000,000', function () {
        expect(fee('S2.LEASE.PREPARE', 1_000_000))->not->toBe(30_000.0);
    });
});
