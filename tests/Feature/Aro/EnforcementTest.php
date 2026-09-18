<?php

declare(strict_types=1);

use App\Domain\Aro\Models\AroVersion;
use Database\Seeders\AroSeeder;

beforeEach(fn () => seedAro());

it('rejects a modifier whose applies_to does not include the item', function () {
    fee('S1.SALE', 10_000_000, modifiers: ['S1.GRANTOR']);
})->throws(InvalidArgumentException::class, 'does not apply to S1.SALE');

it('applies a modifier when applies_to includes the item', function () {
    expect(fee('S1.SECURITY.GRANTEE', 10_000_000, modifiers: ['S1.GRANTOR']))->toBe(71_875.0);
});

it('requires an explicit scale for lower/higher items', function () {
    fee('S7.INSTR', 40_000);
})->throws(InvalidArgumentException::class, "pass scale 'lower' or 'higher'");

it('rejects an invalid scale value', function () {
    fee('S7.INSTR', 40_000, scale: 'bogus');
})->throws(InvalidArgumentException::class);

it('requires an amount for para 4/5 discretionary-add modifiers', function () {
    fee('S1.SALE', 10_000_000, modifiers: ['P4.EXCEPTIONAL_DISPATCH']);
})->throws(InvalidArgumentException::class, 'requires an amount');

it('applies a supplied amount for a discretionary-add modifier', function () {
    expect(fee('S1.SALE', 10_000_000, modifiers: ['P4.EXCEPTIONAL_DISPATCH'], modifierAmounts: ['P4.EXCEPTIONAL_DISPATCH' => 50_000]))
        ->toBe(225_000.0);
});

it('refuses to reseed over a published version', function () {
    AroVersion::firstOrFail()->update(['status' => 'published']);

    (new AroSeeder)->run();
})->throws(RuntimeException::class, 'published');

it('re-seeds a draft version idempotently', function () {
    seedAro();
    expect(AroVersion::firstOrFail()->status)->toBe('draft')
        ->and(AroVersion::firstOrFail()->items()->count())->toBeGreaterThan(100);
});

it('rejects a modifier code that does not exist in the version', function () {
    fee('S1.SALE', 10_000_000, modifiers: ['S1.GRANTOR_TYPO']);
})->throws(InvalidArgumentException::class, 'Unknown modifier code(s) for this ARO version: S1.GRANTOR_TYPO');

it('refuses to price a pointer head', function () {
    fee('S5.CHATTELS.LARGE', 10_000_000);
})->throws(InvalidArgumentException::class, 'not a chargeable head');

it('rejects a zero subject-matter value instead of returning a zero fee', function () {
    fee('S1.SALE', 0);
})->throws(InvalidArgumentException::class, 'positive subject-matter value');

it('rejects a per-unit head with no quantity', function () {
    fee('S6.DRAWING.PLEADING');
})->throws(InvalidArgumentException::class, 'positive quantity');

it('rejects a fractional quantity on a flat head', function () {
    fee('S5.JOURNEY.DAY', quantity: 1.5);
})->throws(InvalidArgumentException::class, 'whole quantity');

it('prints reductions as the resulting figure, never as a negative delta', function () {
    $provenance = computed('S1.SECURITY.GRANTEE', 10_000_000, modifiers: ['S1.GRANTOR'])->provenance();

    expect($provenance)->toContain("× 0.5 = KES\u{A0}71,875.00")
        ->and($provenance)->not->toContain('-KES');
});

it('collapses fixed brackets below the basis into one carry step', function () {
    $steps = computed('S6.INSTR.DEFENDED', 5_000_000)->steps;

    expect($steps)->toHaveCount(2)
        ->and($steps[0]->description)->toBe("fee as for KES\u{A0}1,000,000.00")
        ->and($steps[1]->description)->toBe("2% × KES\u{A0}4,000,000.00");
});

it('removes catalogue rows whose code has left the YAML on reseed', function () {
    $version = AroVersion::firstOrFail();
    $version->items()->create(['code' => 'S99.STALE', 'schedule' => 6, 'label' => 'stale', 'rule_reference' => 'none', 'computation' => 'flat']);
    $version->modifiers()->create(['code' => 'S99.STALE', 'label' => 'stale', 'rule_reference' => 'none', 'op' => 'multiply', 'value' => '1']);

    seedAro();

    expect($version->items()->where('code', 'S99.STALE')->exists())->toBeFalse()
        ->and($version->modifiers()->where('code', 'S99.STALE')->exists())->toBeFalse();
});

it('keeps a reviewed version reviewed on reseed', function () {
    AroVersion::firstOrFail()->update(['status' => 'reviewed']);

    seedAro();

    expect(AroVersion::firstOrFail()->status)->toBe('reviewed');
});
