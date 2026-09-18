<?php

declare(strict_types=1);

use App\Domain\Aro\Models\AroVersion;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\MatterClassification;
use App\Models\Client;
use App\Models\Firm;
use App\Models\Matter;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => seedPublishedAro());

it('renders every advocate-facing page for a firm owner', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $client = Client::factory()->create(['firm_id' => $firm->id]);
    $matter = Matter::factory()->highCourt()->create(['firm_id' => $firm->id, 'client_id' => $client->id]);
    MatterClassification::factory()->head('S6.INSTR.DEFENDED', 5_000_000_00)->create(['matter_id' => $matter->id]);
    $bill = Bill::factory()->create(['matter_id' => $matter->id]);
    $version = AroVersion::firstOrFail();

    $pages = [
        route('dashboard') => 'dashboard',
        route('calculator.index') => 'calculator/index',
        route('clients.index') => 'clients/index',
        route('clients.create') => 'clients/create',
        route('clients.edit', $client) => 'clients/edit',
        route('matters.index') => 'matters/index',
        route('matters.create') => 'matters/create',
        route('matters.show', $matter) => 'matters/show',
        route('matters.edit', $matter) => 'matters/edit',
        route('bills.index') => 'bills/index',
        route('bills.show', $bill) => 'bills/show',
        route('firm.edit') => 'settings/firm',
        route('firms.create') => 'firms/create',
        route('admin.aro.index') => 'admin/aro/index',
        route('admin.aro.show', $version) => 'admin/aro/show',
    ];

    foreach ($pages as $url => $component) {
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
    }
});

it('gives the matter page the catalogue, shortfall and permissions it renders from', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = Matter::factory()->create(['firm_id' => $firm->id]);

    $this->get(route('matters.show', $matter))->assertInertia(fn (Assert $page) => $page
        ->component('matters/show')
        ->where('can.bill', true)
        ->where('shortfall.blocking', false)
        ->has('catalogue', fn (Assert $items) => $items->etc())
        ->where('aroVersion.status', 'published'));
});
