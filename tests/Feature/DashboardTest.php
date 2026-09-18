<?php

namespace Tests\Feature;

use App\Enums\FirmRole;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_users_without_a_firm_are_sent_to_onboarding()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('firms.create'));
    }

    public function test_firm_members_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        Firm::factory()->create()->users()->attach($user, ['role' => FirmRole::Owner->value]);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }
}
