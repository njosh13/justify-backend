<?php

declare(strict_types=1);

use App\Domain\Aro\Engine\Certificates;
use App\Domain\Aro\Engine\Computed;
use App\Domain\Aro\Engine\CostBasis;
use App\Domain\Aro\Engine\CostBasisUplift;
use App\Domain\Aro\Engine\FeeRequest;
use App\Domain\Aro\Engine\GettingUpFee;
use App\Domain\Aro\Engine\Modifiers\ModifierPipeline;
use App\Domain\Aro\Engine\Posture;
use App\Domain\Aro\Engine\Resolver;
use App\Domain\Aro\Services\FeeCalculator;
use Brick\Money\Money;

beforeEach(fn () => seedAro());

describe('Sch 6 item 1(a) — undefended table', function () {
    it('400,000 → 45,000', fn () => expect(fee('S6.INSTR.UNDEFENDED', 400_000))->toBe(45_000.0));
    it('600,000 → 65,000', fn () => expect(fee('S6.INSTR.UNDEFENDED', 600_000))->toBe(65_000.0));
    it('900,000 → 75,000', fn () => expect(fee('S6.INSTR.UNDEFENDED', 900_000))->toBe(75_000.0));
    it('5,000,000 → 145,000', fn () => expect(fee('S6.INSTR.UNDEFENDED', 5_000_000))->toBe(145_000.0));
    it('20,000,000 → 407,500', fn () => expect(fee('S6.INSTR.UNDEFENDED', 20_000_000))->toBe(407_500.0));
    it('50,000,000 → 857,500', fn () => expect(fee('S6.INSTR.UNDEFENDED', 50_000_000))->toBe(857_500.0));
});

describe('Sch 6 item 1(b) — defended table', function () {
    it('400,000 → 75,000', fn () => expect(fee('S6.INSTR.DEFENDED', 400_000))->toBe(75_000.0));
    it('5,000,000 → 200,000', fn () => expect(fee('S6.INSTR.DEFENDED', 5_000_000))->toBe(200_000.0));
    it('20,000,000 → 500,000', fn () => expect(fee('S6.INSTR.DEFENDED', 20_000_000))->toBe(500_000.0));
    it('50,000,000 → 950,000', fn () => expect(fee('S6.INSTR.DEFENDED', 50_000_000))->toBe(950_000.0));
});

describe('Sch 6 postures, getting-up, uplift, certificates', function () {
    it('applies 85% for settled-before-hearing', fn () => expect(fee('S6.INSTR.DEFENDED', 5_000_000, posture: Posture::SettledPreHearing))->toBe(170_000.0));
    it('applies 75% for summary determination', fn () => expect(fee('S6.INSTR.DEFENDED', 5_000_000, posture: Posture::Summary))->toBe(150_000.0));
    it('applies 65% for no appearance on the undefended table', fn () => expect(fee('S6.INSTR.UNDEFENDED', 5_000_000, posture: Posture::NoAppearance))->toBe(94_250.0));

    it('computes getting-up minimum at one-third of instruction fee', function () {
        $instruction = instruction('S6.INSTR.DEFENDED', 5_000_000);
        $gettingUp = GettingUpFee::minimum($instruction);
        expect(round((float) $gettingUp->amount->getAmount()->toFloat()))->toBe(66_667.0)
            ->and($gettingUp->discretionary)->toBeTrue();
    });

    it('applies Part B 50% uplift for advocate-and-client', function () {
        expect(fee('S6.INSTR.DEFENDED', 5_000_000, costBasis: CostBasis::AdvocateClient))->toBe(300_000.0);
    });

    it('applies Part B to instruction + getting-up total (I-6)', function () {
        $instruction = instruction('S6.INSTR.DEFENDED', 5_000_000, CostBasis::AdvocateClient);
        $gettingUp = GettingUpFee::minimum(instruction('S6.INSTR.DEFENDED', 5_000_000));
        $gettingUpAC = CostBasisUplift::advocateClient($gettingUp, 'Sch 6');
        expect(round((float) $instruction->amount->plus($gettingUpAC->amount)->getAmount()->toFloat()))->toBe(400_000.0);
    });

    it('increases instruction fee by half on senior counsel certificate', function () {
        expect(fee('S6.INSTR.DEFENDED', 5_000_000, certificates: new Certificates(seniorCounsel: true)))->toBe(300_000.0);
    });

    it('doubles instruction fee on two-advocates certificate', function () {
        expect(fee('S6.INSTR.DEFENDED', 5_000_000, certificates: new Certificates(twoAdvocates: true)))->toBe(400_000.0);
    });

    it('caps adjournment fee at 15% of instruction fee', function () {
        $instruction = instruction('S6.INSTR.DEFENDED', 5_000_000);
        expect((float) GettingUpFee::adjournmentCap($instruction)->getAmount()->toFloat())->toBe(30_000.0);
    });
});

describe('Sch 6 per-folio and per-unit heads', function () {
    it('drawing pleading ≤4 folios → 1,100', fn () => expect(fee('S6.DRAWING.PLEADING', quantity: 3))->toBe(1_100.0));
    it('drawing pleading 6 folios → 1,400', fn () => expect(fee('S6.DRAWING.PLEADING', quantity: 6))->toBe(1_400.0));
    it('court day lower / higher', function () {
        expect(fee('S6.ATTEND.COURT.DAY.LOWER'))->toBe(10_000.0)
            ->and(fee('S6.ATTEND.COURT.DAY.HIGHER'))->toBe(15_000.0);
    });
    it('service at 10 km → 1,400 + 7×35', fn () => expect(fee('S6.SERVICE.PER_KM', quantity: 10))->toBe(1_645.0));
});

function instruction(string $code, float $basis, CostBasis $costBasis = CostBasis::PartyParty): Computed
{
    $calc = new FeeCalculator(new Resolver, new ModifierPipeline);

    return $calc->minimum(new FeeRequest(
        version: aroVersion(),
        itemCode: $code,
        basis: Money::of((string) $basis, 'KES'),
        costBasis: $costBasis,
    ));
}
