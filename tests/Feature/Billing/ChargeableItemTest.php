<?php

declare(strict_types=1);

use App\Domain\Aro\Models\AroItem;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\ChargeableItem;
use App\Domain\Billing\Models\FeeAgreement;
use App\Domain\Billing\Models\MatterClassification;
use App\Enums\FirmRole;
use App\Models\Firm;
use App\Models\Matter;

beforeEach(fn () => seedPublishedAro());

function conveyancingMatter(Firm $firm, int $basisCents = 10_000_000_00): Matter
{
    $matter = Matter::factory()->create(['firm_id' => $firm->id]);
    MatterClassification::factory()->head('S1.SALE', $basisCents)->create(['matter_id' => $matter->id]);

    return $matter;
}

function feeLine(array $overrides = []): array
{
    return [
        'kind' => 'fee',
        'aro_item_id' => aroItemId('S1.SALE'),
        'description' => 'Sale of LR 1234',
        'occurred_on' => '2026-09-01',
        'entered' => 175_000,
        ...$overrides,
    ];
}

function aroItemId(string $code): string
{
    return AroItem::query()->where('code', $code)->value('id');
}

it('stores a fee line with the engine minimum and provenance', function () {
    $firm = Firm::factory()->create();
    $user = actingAsMemberOf($firm);
    $matter = conveyancingMatter($firm);

    $this->post(route('matters.chargeable-items.store', $matter), feeLine())
        ->assertRedirect(route('matters.show', $matter))
        ->assertSessionHasNoErrors();

    $item = ChargeableItem::query()->firstOrFail();
    expect($item->computed_minimum_cents)->toBe(175_000_00)
        ->and($item->computed_bound)->toBe('prescribed')
        ->and($item->advocate_id)->toBe($user->id)
        ->and($item->computed_snapshot['provenance'])->toContain('Sch 1, First Scale, para 1')
        ->and($item->shortfallCents())->toBe(0);
});

it('rejects a fee below the statutory minimum with the minimum in the message', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = conveyancingMatter($firm);

    $this->from(route('matters.show', $matter))
        ->post(route('matters.chargeable-items.store', $matter), feeLine(['entered' => 100_000]))
        ->assertRedirect(route('matters.show', $matter))
        ->assertSessionHasErrors('entered');

    expect(sessionError('entered'))->toContain('175,000.00')->toContain('Para 3');

    expect(ChargeableItem::query()->count())->toBe(0);
});

it('accepts a fee below scale when the matter is exempt', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = Matter::factory()->create(['firm_id' => $firm->id]);
    MatterClassification::factory()->head('S1.SALE', 10_000_000_00)->exempt('pro bono')->create(['matter_id' => $matter->id]);

    $this->post(route('matters.chargeable-items.store', $matter), feeLine(['entered' => 50_000]))
        ->assertSessionHasNoErrors();

    expect(ChargeableItem::query()->firstOrFail()->shortfallCents())->toBe(125_000_00);
});

it('accepts a fee below scale when a para 22 election has been communicated', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = conveyancingMatter($firm);
    FeeAgreement::factory()->schedule5Election()->create(['matter_id' => $matter->id]);

    $this->post(route('matters.chargeable-items.store', $matter), feeLine(['entered' => 50_000]))
        ->assertSessionHasNoErrors();
});

it('still rejects a fee below scale when the election was never communicated in writing', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = conveyancingMatter($firm);
    FeeAgreement::factory()->schedule5Election(communicated: false)->create(['matter_id' => $matter->id]);

    $this->post(route('matters.chargeable-items.store', $matter), feeLine(['entered' => 50_000]))
        ->assertSessionHasErrors(['entered']);
});

it('requires a justification for charging above the scale figure', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = conveyancingMatter($firm);

    $this->post(route('matters.chargeable-items.store', $matter), feeLine(['entered' => 200_000]))
        ->assertSessionHasErrors(['uplift_justification']);

    $this->post(route('matters.chargeable-items.store', $matter), feeLine(['entered' => 200_000, 'uplift_justification' => 'Complex title, three consents (para 5)']))
        ->assertSessionHasNoErrors();
});

it('rejects a charge above a "not exceeding" head', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = Matter::factory()->create(['firm_id' => $firm->id]);
    MatterClassification::factory()->create(['matter_id' => $matter->id]);

    // Sch 9 Part B: the 25,000 ceiling is itself increased by 50% advocate-and-client (the firm's default basis).
    $this->post(route('matters.chargeable-items.store', $matter), feeLine(['aro_item_id' => aroItemId('S9.NON_PECUNIARY.OPPOSED'), 'description' => 'Opposed complaint', 'entered' => 40_000]))
        ->assertSessionHasErrors('entered');
    expect(sessionError('entered'))->toContain('maximum')->toContain('37,500.00');

    $this->post(route('matters.chargeable-items.store', $matter), feeLine(['aro_item_id' => aroItemId('S9.NON_PECUNIARY.OPPOSED'), 'description' => 'Opposed complaint', 'entered' => 18_000]))
        ->assertSessionHasNoErrors();
});

it('reports engine input problems as a pricing error instead of a 500', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = Matter::factory()->create(['firm_id' => $firm->id]);
    MatterClassification::factory()->create(['matter_id' => $matter->id]);

    $this->post(route('matters.chargeable-items.store', $matter), feeLine(['aro_item_id' => aroItemId('S7.INSTR'), 'entered' => 30_000, 'basis_override' => 150_000]))
        ->assertSessionHasErrors('pricing');
    expect(sessionError('pricing'))->toContain("scale 'lower' or 'higher'");
});

it('prices getting-up from the instruction fee line on the matter', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = Matter::factory()->highCourt()->create(['firm_id' => $firm->id]);
    MatterClassification::factory()->head('S6.INSTR.DEFENDED', 5_000_000_00)->create(['matter_id' => $matter->id]);

    $this->post(route('matters.chargeable-items.store', $matter), feeLine(['aro_item_id' => aroItemId('S6.GETTING_UP'), 'description' => 'Getting up', 'entered' => 100_000]))
        ->assertSessionHasErrors('pricing');
    expect(sessionError('pricing'))->toContain('instruction fee line');

    $this->post(route('matters.chargeable-items.store', $matter), feeLine(['aro_item_id' => aroItemId('S6.INSTR.DEFENDED'), 'description' => 'Instructions to defend', 'entered' => 300_000]))
        ->assertSessionHasNoErrors();
    $this->post(route('matters.chargeable-items.store', $matter), feeLine(['aro_item_id' => aroItemId('S6.GETTING_UP'), 'description' => 'Getting up', 'entered' => 150_000]))
        ->assertSessionHasNoErrors();

    $gettingUp = ChargeableItem::query()->where('description', 'Getting up')->firstOrFail();
    expect($gettingUp->computed_minimum_cents)->toBe(150_000_00)
        ->and($gettingUp->computed_bound)->toBe('minimum');
});

it('keeps the matter posture and certificates away from heads that do not take them', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = Matter::factory()->highCourt()->create(['firm_id' => $firm->id]);
    MatterClassification::factory()->head('S6.INSTR.DEFENDED', 5_000_000_00)->create([
        'matter_id' => $matter->id, 'posture' => 'summary', 'certificates' => ['senior_counsel' => true],
    ]);

    $this->post(route('matters.chargeable-items.store', $matter), feeLine(['aro_item_id' => aroItemId('S6.INSTR.DEFENDED'), 'description' => 'Instructions', 'entered' => 337_500]))
        ->assertSessionHasNoErrors();
    $this->post(route('matters.chargeable-items.store', $matter), feeLine(['aro_item_id' => aroItemId('S6.DRAWING.PLEADING'), 'description' => 'Drawing plaint', 'quantity' => 6, 'entered' => 2_100]))
        ->assertSessionHasNoErrors();

    $instruction = ChargeableItem::query()->where('description', 'Instructions')->firstOrFail();
    $drawing = ChargeableItem::query()->where('description', 'Drawing plaint')->firstOrFail();

    // 200,000 × 75% summary × 1.5 senior counsel × 1.5 advocate-client = 337,500; drawing 1,100 + 2 × 150 = 1,400 × 1.5.
    expect($instruction->computed_minimum_cents)->toBe(337_500_00)
        ->and($drawing->computed_minimum_cents)->toBe(2_100_00);
});

it('stores a disbursement without pricing it', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = conveyancingMatter($firm);

    $this->post(route('matters.chargeable-items.store', $matter), ['kind' => 'disbursement', 'description' => 'Stamp duty', 'occurred_on' => '2026-09-02', 'entered' => 400_000])
        ->assertSessionHasNoErrors();

    expect(ChargeableItem::query()->firstOrFail())
        ->computed_minimum_cents->toBeNull()
        ->entered_cents->toBe(400_000_00);
});

it('forbids read-only members from adding work', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm, FirmRole::ReadOnly);
    $matter = conveyancingMatter($firm);

    $this->post(route('matters.chargeable-items.store', $matter), feeLine())->assertForbidden();
});

it('refuses to edit or delete a billed line', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = conveyancingMatter($firm);
    $bill = Bill::factory()->create(['matter_id' => $matter->id]);
    $item = ChargeableItem::factory()->disbursement(1_000_00)->create(['matter_id' => $matter->id, 'bill_id' => $bill->id]);

    $this->put(route('matters.chargeable-items.update', [$matter, $item]), ['kind' => 'disbursement', 'description' => 'x', 'occurred_on' => '2026-09-01', 'entered' => 5])->assertForbidden();
    $this->delete(route('matters.chargeable-items.destroy', [$matter, $item]))->assertForbidden();
});
