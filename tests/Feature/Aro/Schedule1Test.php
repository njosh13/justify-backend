<?php

declare(strict_types=1);

beforeEach(fn () => seedAro());

describe('Sch 1 First Scale — S1.SALE', function () {
    it('floors at 35,000 below the 2% threshold', fn () => expect(fee('S1.SALE', 1_000_000))->toBe(35_000.0));
    it('equals floor exactly at 1,750,000', fn () => expect(fee('S1.SALE', 1_750_000))->toBe(35_000.0));
    it('computes top of band 1', fn () => expect(fee('S1.SALE', 5_000_000))->toBe(100_000.0));
    it('accumulates band 2', fn () => expect(fee('S1.SALE', 10_000_000))->toBe(175_000.0));
    it('accumulates to band 3', fn () => expect(fee('S1.SALE', 100_000_000))->toBe(1_525_000.0));
    it('accumulates to band 4', fn () => expect(fee('S1.SALE', 250_000_000))->toBe(3_400_000.0));
    it('accumulates to band 5', fn () => expect(fee('S1.SALE', 1_000_000_000))->toBe(10_900_000.0));
    it('accumulates the open band', fn () => expect(fee('S1.SALE', 2_000_000_000))->toBe(11_900_000.0));

    it('reduces by one-third when vendor did not prepare agreement', function () {
        expect(round(fee('S1.SALE', 10_000_000, modifiers: ['S1.VENDOR_NO_AGREEMENT'])))->toBe(116_667.0);
    });
});

describe('Sch 1 Second Scale — S1.SECURITY.GRANTEE', function () {
    it('floors at 28,000', fn () => expect(fee('S1.SECURITY.GRANTEE', 1_000_000))->toBe(28_000.0));
    it('tops band 1', fn () => expect(fee('S1.SECURITY.GRANTEE', 2_500_000))->toBe(50_000.0));
    it('accumulates band 2', fn () => expect(fee('S1.SECURITY.GRANTEE', 5_000_000))->toBe(93_750.0));
    it('accumulates band 3', fn () => expect(fee('S1.SECURITY.GRANTEE', 10_000_000))->toBe(143_750.0));
    it('accumulates band 4', fn () => expect(fee('S1.SECURITY.GRANTEE', 100_000_000))->toBe(1_043_750.0));
    it('accumulates band 5', fn () => expect(fee('S1.SECURITY.GRANTEE', 250_000_000))->toBe(2_168_750.0));

    it('halves for the grantor\'s advocate', fn () => expect(fee('S1.SECURITY.GRANTEE', 10_000_000, modifiers: ['S1.GRANTOR']))->toBe(71_875.0));

    it('applies discharge-with-undertaking quarter fee', function () {
        expect(round(fee('S1.SECURITY.GRANTEE', 10_000_000, modifiers: ['S1.DISCHARGE.UNDERTAKING', 'S1.DISCHARGE.UNDERTAKING.FLOOR'])))->toBe(35_938.0);
    });

    it('floors discharge-without-undertaking at 10,000', function () {
        expect(fee('S1.SECURITY.GRANTEE', 1_000_000, modifiers: ['S1.DISCHARGE.NO_UNDERTAKING', 'S1.DISCHARGE.NO_UNDERTAKING.FLOOR']))->toBe(10_000.0);
    });

    it('floors grantor discharge at 15,000', function () {
        expect(fee('S1.SECURITY.GRANTEE', 1_000_000, modifiers: ['S1.GRANTOR.DISCHARGE', 'S1.GRANTOR.DISCHARGE.FLOOR']))->toBe(15_000.0);
    });

    it('halves equitable mortgage creation', function () {
        expect(fee('S1.SECURITY.GRANTEE', 1_000_000, modifiers: ['S1.EQUITABLE', 'S1.EQUITABLE.FLOOR']))->toBe(14_000.0);
    });

    it('caps equitable discharge at 42,000', function () {
        expect(fee('S1.SECURITY.GRANTEE', 100_000_000, modifiers: ['S1.EQUITABLE.DISCHARGE', 'S1.EQUITABLE.DISCHARGE.FLOOR', 'S1.EQUITABLE.DISCHARGE.CAP']))->toBe(42_000.0);
    });

    it('floors equitable discharge at 10,000', function () {
        expect(fee('S1.SECURITY.GRANTEE', 1_000_000, modifiers: ['S1.EQUITABLE.DISCHARGE', 'S1.EQUITABLE.DISCHARGE.FLOOR', 'S1.EQUITABLE.DISCHARGE.CAP']))->toBe(10_000.0);
    });

    it('adds 25% when one advocate acts for both sides (Note 3)', function () {
        expect(round(fee('S1.SECURITY.GRANTEE', 10_000_000, modifiers: ['S1.BOTH_SIDES'])))->toBe(179_688.0);
    });

    it('charges second security at 25% and third at 10% as separate lines (Note 5)', function () {
        $first = fee('S1.SECURITY.GRANTEE', 10_000_000);
        $second = fee('S1.SECURITY.GRANTEE', 10_000_000, modifiers: ['S1.ADDITIONAL_SECURITY.FIRST']);
        $third = fee('S1.SECURITY.GRANTEE', 10_000_000, modifiers: ['S1.ADDITIONAL_SECURITY.SUBSEQUENT']);
        expect(round($first + $second + $third))->toBe(194_063.0);
    });

    it('charges second property at 10% and third at 5% as separate lines (Note 6)', function () {
        $first = fee('S1.SECURITY.GRANTEE', 10_000_000);
        $second = fee('S1.SECURITY.GRANTEE', 10_000_000, modifiers: ['S1.ADDITIONAL_PROPERTY.SECOND']);
        $third = fee('S1.SECURITY.GRANTEE', 10_000_000, modifiers: ['S1.ADDITIONAL_PROPERTY.SUBSEQUENT']);
        expect(round($first + $second + $third))->toBe(165_313.0);
    });

    it('adds 5% per additional grantor, divided equally (Note 7)', function () {
        $first = fee('S1.SECURITY.GRANTEE', 10_000_000);
        $g2 = fee('S1.SECURITY.GRANTEE', 10_000_000, modifiers: ['S1.ADDITIONAL_GRANTOR']);
        $g3 = fee('S1.SECURITY.GRANTEE', 10_000_000, modifiers: ['S1.ADDITIONAL_GRANTOR']);
        expect($first + $g2 + $g3)->toBe(158_125.0);
        expect(round(($first + $g2 + $g3) / 3, 2))->toBe(52_708.33);
    });

    it('reduces printed-form security by one-third', function () {
        expect(round(fee('S1.SECURITY.GRANTEE', 10_000_000, modifiers: ['S1.PRINTED_FORM', 'S1.PRINTED_FORM.HALF_FLOOR'])))->toBe(95_833.0);
    });

    it('caps combined equitable + printed-form reductions at half the scale fee', function () {
        expect(fee('S1.SECURITY.GRANTEE', 10_000_000, modifiers: ['S1.EQUITABLE', 'S1.EQUITABLE.FLOOR', 'S1.PRINTED_FORM', 'S1.PRINTED_FORM.HALF_FLOOR']))->toBe(71_875.0);
    });
});

describe('Sch 1 Third Scale — S1.NEGOTIATION', function () {
    it('charges 100 units at 112', fn () => expect(fee('S1.NEGOTIATION', 200_000))->toBe(11_200.0));
    it('counts a remainder ≤ 1,000 as half a unit', fn () => expect(fee('S1.NEGOTIATION', 201_000))->toBe(11_226.0));
    it('counts a remainder > 1,000 as a full unit', fn () => expect(fee('S1.NEGOTIATION', 202_000))->toBe(11_252.0));
    it('accumulates all three unit bands', fn () => expect(fee('S1.NEGOTIATION', 1_000_000))->toBe(27_600.0));
});
