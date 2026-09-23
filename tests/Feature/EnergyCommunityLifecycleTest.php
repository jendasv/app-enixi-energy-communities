<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CommunityRole;
use App\Enums\EnergyCommunityMeterPointState;
use App\Enums\EnergyCommunityState;
use App\Enums\EnergyDirection;
use App\Models\EnergyCommunity;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\MeterPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnergyCommunityLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function communityWithManager(User $manager, EnergyCommunityState $state = EnergyCommunityState::New): EnergyCommunity
    {
        $community = EnergyCommunity::factory()->create(['state' => $state]);
        $community->users()->attach($manager->id, ['role' => CommunityRole::Manager]);

        return $community;
    }

    public function test_guest_cannot_activate_a_community(): void
    {
        $community = EnergyCommunity::factory()->create();

        $response = $this->postJson("/api/energy-communities/{$community->id}/activate");

        $response->assertUnauthorized();
    }

    public function test_member_cannot_activate_a_community(): void
    {
        $member = User::factory()->create();
        $community = EnergyCommunity::factory()->create();
        $community->users()->attach($member->id, ['role' => CommunityRole::Member]);
        Sanctum::actingAs($member);

        $response = $this->postJson("/api/energy-communities/{$community->id}/activate");

        $response->assertForbidden();
    }

    public function test_activation_fails_without_an_accepted_generation_registration(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$community->id}/activate");

        $response->assertUnprocessable();
    }

    public function test_activation_fails_with_only_a_consumption_registration(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager);
        $meterPoint = MeterPoint::factory()->create(['energy_direction' => EnergyDirection::Consumption]);
        EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'meter_point_id' => $meterPoint->id,
            'state' => EnergyCommunityMeterPointState::Accepted,
        ]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$community->id}/activate");

        $response->assertUnprocessable();
    }

    public function test_manager_can_activate_with_an_accepted_generation_registration(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager);
        $meterPoint = MeterPoint::factory()->create(['energy_direction' => EnergyDirection::Generation]);
        EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'meter_point_id' => $meterPoint->id,
            'state' => EnergyCommunityMeterPointState::Accepted,
        ]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$community->id}/activate");

        $response->assertOk();
        $response->assertJsonPath('data.state', EnergyCommunityState::Activated->value);
    }

    public function test_activation_fails_when_not_in_new_state(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager, EnergyCommunityState::Activated);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$community->id}/activate");

        $response->assertUnprocessable();
    }

    public function test_guest_cannot_reject_a_community(): void
    {
        $community = EnergyCommunity::factory()->create();

        $response = $this->postJson("/api/energy-communities/{$community->id}/reject");

        $response->assertUnauthorized();
    }

    public function test_member_cannot_reject_a_community(): void
    {
        $member = User::factory()->create();
        $community = EnergyCommunity::factory()->create();
        $community->users()->attach($member->id, ['role' => CommunityRole::Member]);
        Sanctum::actingAs($member);

        $response = $this->postJson("/api/energy-communities/{$community->id}/reject");

        $response->assertForbidden();
    }

    public function test_manager_can_reject_a_new_community(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager, EnergyCommunityState::New);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$community->id}/reject");

        $response->assertOk();
        $response->assertJsonPath('data.state', EnergyCommunityState::Rejected->value);
    }

    public function test_manager_can_reject_an_activated_community(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager, EnergyCommunityState::Activated);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$community->id}/reject");

        $response->assertOk();
        $response->assertJsonPath('data.state', EnergyCommunityState::Rejected->value);
    }

    public function test_rejecting_an_already_rejected_community_fails(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager, EnergyCommunityState::Rejected);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$community->id}/reject");

        $response->assertUnprocessable();
    }

    public function test_rejecting_ends_every_blocking_registration(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager, EnergyCommunityState::Activated);

        $accepted = EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'state' => EnergyCommunityMeterPointState::Accepted,
            'from_date' => '2026-01-01',
            'to_date' => null,
        ]);
        $new = EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'state' => EnergyCommunityMeterPointState::New,
        ]);
        $alreadyRemoved = EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'state' => EnergyCommunityMeterPointState::Removed,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$community->id}/reject");

        $response->assertOk();
        $this->assertSame(EnergyCommunityMeterPointState::Deactivated, $accepted->refresh()->state);
        $this->assertSame(now()->toDateString(), $accepted->fresh()->to_date->toDateString());
        $this->assertSame(EnergyCommunityMeterPointState::Removed, $new->refresh()->state);
        // Was already removed (non-blocking) before the reject — untouched.
        $this->assertSame(EnergyCommunityMeterPointState::Removed, $alreadyRemoved->refresh()->state);
    }
}
