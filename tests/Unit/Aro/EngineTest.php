<?php

declare(strict_types=1);

use App\Domain\Aro\Engine\Computed;
use App\Domain\Aro\Engine\FolioCounter;
use App\Domain\Billing\Services\InterestCalculator;
use App\Domain\Billing\Services\TaxationRiskScorer;
use App\Domain\Tax\Vat\VatCalculator;
use App\Domain\Tax\Vat\WithholdingTaxCalculator;
use Brick\Money\Money;
use Carbon\CarbonImmutable;

describe('FolioCounter — para 17', function () {
    it('counts no folios in empty text', fn () => expect(FolioCounter::folios(''))->toBe(0));
    it('counts 100 words as one folio', fn () => expect(FolioCounter::folios(str_repeat('word ', 100)))->toBe(1));
    it('counts any part of a folio as one folio', fn () => expect(FolioCounter::folios(str_repeat('word ', 101)))->toBe(2));
    it('counts 250 words as three folios', fn () => expect(FolioCounter::folios(str_repeat('word ', 250)))->toBe(3));
    it('counts "Kshs. 25,564" as one word', fn () => expect(FolioCounter::words('Kshs. 25,564'))->toBe(1));
});

describe('Interest — para 7', function () {
    $calc = new InterestCalculator;
    $principal = Money::of(1_000_000, 'KES');
    $delivered = CarbonImmutable::parse('2026-01-01');

    it('accrues 14% p.a. simple from one month after delivery', function () use ($calc, $principal, $delivered) {
        $accrued = $calc->accrued($principal, $delivered, CarbonImmutable::parse('2026-02-15'), null, CarbonImmutable::parse('2026-03-03'));
        expect((float) $accrued->getAmount()->toFloat())->toBe(11_506.85);
    });

    it('pays nothing when the claim is raised after full payment', function () use ($calc, $principal, $delivered) {
        expect($calc->accrued($principal, $delivered, CarbonImmutable::parse('2026-02-15'), CarbonImmutable::parse('2026-02-10'), CarbonImmutable::parse('2026-03-03'))->isZero())->toBeTrue();
    });

    it('pays nothing when interest was never claimed', function () use ($calc, $principal, $delivered) {
        expect($calc->accrued($principal, $delivered, null, null, CarbonImmutable::parse('2026-03-03'))->isZero())->toBeTrue();
    });

    it('pays nothing before the one-month mark', function () use ($calc, $principal, $delivered) {
        expect($calc->accrued($principal, $delivered, CarbonImmutable::parse('2026-01-15'), null, CarbonImmutable::parse('2026-01-20'))->isZero())->toBeTrue();
    });
});

describe('Para 6 deemed agreement', function () {
    it('is one calendar month after delivery', fn () => expect(CarbonImmutable::parse('2026-03-15')->addMonthNoOverflow()->toDateString())->toBe('2026-04-15'));
    it('clamps at month end', fn () => expect(CarbonImmutable::parse('2026-01-31')->addMonthNoOverflow()->toDateString())->toBe('2026-02-28'));
});

describe('Para 11 objection deadline', function () {
    it('is 14 days after the certificate', fn () => expect(CarbonImmutable::parse('2026-05-01')->addDays(14)->toDateString())->toBe('2026-05-15'));
});

describe('Para 77 one-sixth rule', function () {
    it('breached when more than one-sixth taxed off', function () {
        expect(TaxationRiskScorer::oneSixthBreached(Money::of(600_000, 'KES'), Money::of(120_000, 'KES')))->toBeTrue();
    });
    it('not breached at exactly one-sixth', function () {
        expect(TaxationRiskScorer::oneSixthBreached(Money::of(600_000, 'KES'), Money::of(100_000, 'KES')))->toBeFalse();
    });
});

describe('Computed provenance', function () {
    it('produces an ordered, cited step list', function () {
        $c = Computed::zero()
            ->add('Sch 1 band 1', '2% × KES 1,000,000', Money::of(20_000, 'KES'))
            ->replace('Sch 1 band 1 floor', 'or KES 35,000 whichever is higher', Money::of(35_000, 'KES'));

        expect($c->provenance())->toContain('Sch 1 band 1')
            ->and($c->provenance())->toContain('35,000');
    });
});

describe('VAT / WHT — §8', function () {
    it('computes the worked invoice', function () {
        $taxable = VatCalculator::taxable(Money::of(100_000, 'KES'), Money::of(5_000, 'KES'));
        $vat = VatCalculator::vat($taxable);
        expect((float) $taxable->getAmount()->toFloat())->toBe(105_000.0)
            ->and((float) $vat->getAmount()->toFloat())->toBe(16_800.0)
            ->and((float) $taxable->plus($vat)->plus(Money::of(20_000, 'KES'))->getAmount()->toFloat())->toBe(141_800.0);
    });

    it('expects 5% WHT from withholding agents', function () {
        expect((float) WithholdingTaxCalculator::expected(Money::of(105_000, 'KES'), true)->getAmount()->toFloat())->toBe(5_250.0);
    });

    it('expects no WHT below the monthly threshold', function () {
        expect(WithholdingTaxCalculator::expected(Money::of(20_000, 'KES'), true)->isZero())->toBeTrue();
    });

    it('expects no WHT from non-agents', function () {
        expect(WithholdingTaxCalculator::expected(Money::of(105_000, 'KES'), false)->isZero())->toBeTrue();
    });

    it('zero-rates exempt clients with tax type A', function () {
        expect(VatCalculator::vat(Money::of(105_000, 'KES'), exempt: true)->isZero())->toBeTrue()
            ->and(VatCalculator::taxTypeCode(false, true))->toBe('A')
            ->and(VatCalculator::taxTypeCode(true, false))->toBe('D');
    });
});
