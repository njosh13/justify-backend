<?php

namespace Tests;

use App\Enums\FirmRole;
use App\Models\Firm;
use App\Models\User;
use App\Tenancy\CurrentFirm;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /** Sign in as a member of a fresh firm and make it the current firm. */
    protected function actingAsMember(FirmRole $role = FirmRole::Owner, ?Firm $firm = null): User
    {
        $firm ??= Firm::factory()->create();
        $user = User::factory()->create(['current_firm_id' => $firm->id]);
        $firm->users()->attach($user, ['role' => $role->value]);

        $this->actingAs($user);
        app(CurrentFirm::class)->set($firm);

        return $user;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
