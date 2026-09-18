<?php

declare(strict_types=1);

use App\Enums\FirmRole;
use App\Models\Firm;
use App\Models\User;

it('creates a firm and makes the creator its owner', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('firms.store'), [
        'name' => 'Kimondo & Co. Advocates',
        'kra_pin' => 'P051234567X',
        'vat_registered' => true,
        'rounding_policy' => 'shilling_half_up',
        'default_cost_basis' => 'advocate_client',
        'bill_number_prefix' => 'KC',
    ])->assertRedirect(route('dashboard'));

    $firm = Firm::query()->where('name', 'Kimondo & Co. Advocates')->firstOrFail();

    expect($user->refresh()->roleIn($firm))->toBe(FirmRole::Owner)
        ->and($user->current_firm_id)->toBe($firm->id);
});

it('requires a name and known rounding policy', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('firms.store'), ['rounding_policy' => 'nearest_thousand'])
        ->assertSessionHasErrors(['name', 'rounding_policy', 'default_cost_basis', 'bill_number_prefix']);
});

it('lets only owners and admins edit firm settings', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm, FirmRole::Advocate);

    $this->get(route('firm.edit'))->assertForbidden();
    $this->patch(route('firm.update'), ['name' => 'Renamed'])->assertForbidden();
});
