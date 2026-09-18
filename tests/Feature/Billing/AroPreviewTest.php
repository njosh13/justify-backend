<?php

declare(strict_types=1);

use App\Models\Firm;

beforeEach(fn () => seedPublishedAro());

it('returns the engine figure and steps for a head', function () {
    actingAsMemberOf(Firm::factory()->create());

    $this->postJson(route('aro.preview'), ['item_code' => 'S1.SALE', 'basis' => 10_000_000])
        ->assertOk()
        ->assertJsonPath('amount_cents', 175_000_00)
        ->assertJsonPath('bound', 'prescribed')
        ->assertJsonPath('item.code', 'S1.SALE')
        ->assertJsonPath('steps.0.rule_ref', 'Sch 1, First Scale, para 1 band 1');
});

it('applies posture, certificates and cost basis', function () {
    actingAsMemberOf(Firm::factory()->create());

    $this->postJson(route('aro.preview'), [
        'item_code' => 'S6.INSTR.DEFENDED', 'basis' => 5_000_000, 'posture' => 'summary',
        'certificates' => ['senior_counsel' => true], 'cost_basis' => 'advocate_client',
    ])->assertOk()->assertJsonPath('amount_cents', 337_500_00);
});

it('returns 422 with the engine message for a bad combination', function () {
    actingAsMemberOf(Firm::factory()->create());

    $this->postJson(route('aro.preview'), ['item_code' => 'S6.INSTR.DEFENDED', 'basis' => 5_000_000, 'posture' => 'no_appearance'])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn (string $m) => str_contains($m, 'does not apply to the defended table'));
});

it('validates the payload', function () {
    actingAsMemberOf(Firm::factory()->create());

    $this->postJson(route('aro.preview'), ['basis' => 'lots'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['item_code', 'basis']);
});

it('lists the catalogue with the inputs each head needs', function () {
    actingAsMemberOf(Firm::factory()->create());

    $this->getJson(route('aro.items', ['schedule' => 7, 'q' => 'instruction']))
        ->assertOk()
        ->assertJsonPath('items.0.code', 'S7.INSTR')
        ->assertJsonPath('items.0.needs.scale', true)
        ->assertJsonPath('items.0.needs.posture', true)
        ->assertJsonPath('items.0.needs.posture_table.lower', 'a');
});
