<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\ChargeableItem;
use App\Models\Client;
use App\Models\Firm;
use App\Models\Matter;
use App\Tenancy\CurrentFirm;

beforeEach(fn () => seedPublishedAro());

it('returns 404 for another firm\'s client, matter and bill', function () {
    $other = Firm::factory()->create();
    $client = Client::factory()->create(['firm_id' => $other->id]);
    $matter = Matter::factory()->create(['firm_id' => $other->id, 'client_id' => $client->id]);
    $bill = Bill::factory()->create(['matter_id' => $matter->id]);

    actingAsMemberOf(Firm::factory()->create());

    $this->get(route('clients.edit', $client))->assertNotFound();
    $this->get(route('matters.show', $matter))->assertNotFound();
    $this->get(route('bills.show', $bill))->assertNotFound();
    $this->get(route('bills.pdf', $bill))->assertNotFound();
});

it('scopes listings to the current firm', function () {
    $mine = Firm::factory()->create();
    $other = Firm::factory()->create();
    Client::factory()->create(['firm_id' => $mine->id, 'full_name' => 'Wanjiku Mine']);
    Client::factory()->create(['firm_id' => $other->id, 'full_name' => 'Otieno Other']);

    actingAsMemberOf($mine);

    $this->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Wanjiku Mine')
        ->assertDontSee('Otieno Other');
});

it('ignores a mass-assigned firm_id on create', function () {
    $mine = Firm::factory()->create();
    $other = Firm::factory()->create();
    actingAsMemberOf($mine);

    $this->post(route('clients.store'), ['full_name' => 'Smuggled', 'client_type' => 'individual', 'firm_id' => $other->id])
        ->assertRedirect(route('clients.index'));

    expect(Client::withoutGlobalScopes()->where('full_name', 'Smuggled')->value('firm_id'))->toBe($mine->id);
});

it('cannot write chargeable work onto another firm\'s matter through nested routes', function () {
    $other = Firm::factory()->create();
    $matter = Matter::factory()->create(['firm_id' => $other->id]);
    $item = ChargeableItem::factory()->create(['matter_id' => $matter->id]);

    actingAsMemberOf(Firm::factory()->create());

    $this->post(route('matters.chargeable-items.store', $matter), ['kind' => 'disbursement', 'description' => 'x', 'occurred_on' => '2026-09-01', 'entered' => 100])
        ->assertNotFound();
    $this->delete(route('matters.chargeable-items.destroy', [$matter, $item]))->assertNotFound();

    expect(ChargeableItem::withoutGlobalScopes()->whereKey($item->id)->exists())->toBeTrue();
});

it('resolves the current firm from the user\'s chosen membership', function () {
    $a = Firm::factory()->create(['name' => 'Firm A']);
    $b = Firm::factory()->create(['name' => 'Firm B']);
    $user = actingAsMemberOf($a);
    $b->users()->attach($user, ['role' => 'advocate']);
    app(CurrentFirm::class)->forget();

    $this->put(route('current-firm.update'), ['firm_id' => $b->id])->assertRedirect(route('dashboard'));
    app(CurrentFirm::class)->forget();

    expect($user->refresh()->resolveCurrentFirm()?->id)->toBe($b->id);
});

it('refuses to switch to a firm the user is not a member of', function () {
    $mine = Firm::factory()->create();
    $other = Firm::factory()->create();
    actingAsMemberOf($mine);

    $this->put(route('current-firm.update'), ['firm_id' => $other->id])->assertNotFound();
});
