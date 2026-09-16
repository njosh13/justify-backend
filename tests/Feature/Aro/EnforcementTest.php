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
