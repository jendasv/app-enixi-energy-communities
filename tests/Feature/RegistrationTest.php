<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CommunityRole;
use App\Enums\EnergyCommunityMeterPointState;
use App\Enums\EnergyCommunityState;
use App\Models\EnergyCommunity;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\MeterPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function communityWithManager(User $manager): EnergyCommunity
    {
        $community = EnergyCommunity::factory()->create(['state' => EnergyCommunityState::Activated]);
        $community->users()->attach($manager->id, ['role' => CommunityRole::Manager]);

        return $community;
    }

    public function test_guest_cannot_register_a_meter_point(): void
    {
        $community = EnergyCommunity::factory()->create();

        $response = $this->postJson("/api/energy-communities/{$community->id}/meter-points", []);

        $response->assertUnauthorized();
    }

    public function test_member_cannot_register_a_meter_point(): void
    {
        $member = User::factory()->create();
        $community = EnergyCommunity::factory()->create(['state' => EnergyCommunityState::Activated]);
        $community->users()->attach($member->id, ['role' => CommunityRole::Member]);
        $meterPoint = MeterPoint::factory()->for($member)->create();
        Sanctum::actingAs($member);

        $response = $this->postJson("/api/energy-communities/{$community->id}/meter-points", [
            'meter_point_id' => $meterPoint->id,
            'from_date' => '2026-04-01',
            'consent_date' => '2026-03-20',
        ]);

        $response->assertForbidden();
    }

    public function test_manager_can_register_a_meter_point(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager);
        $owner = User::factory()->create();
        $community->users()->attach($owner->id, ['role' => CommunityRole::Member]);
        $meterPoint = MeterPoint::factory()->for($owner)->create();
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$community->id}/meter-points", [
            'meter_point_id' => $meterPoint->id,
            'from_date' => '2026-04-01',
            'to_date' => null,
            'consent_date' => '2026-03-20',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.state', EnergyCommunityMeterPointState::New->value);
    }

    public function test_registration_fails_when_community_is_rejected(): void
    {
        $manager = User::factory()->create();
        $community = EnergyCommunity::factory()->create(['state' => EnergyCommunityState::Rejected]);
        $community->users()->attach($manager->id, ['role' => CommunityRole::Manager]);
        $meterPoint = MeterPoint::factory()->for($manager)->create();
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$community->id}/meter-points", [
            'meter_point_id' => $meterPoint->id,
            'from_date' => '2026-04-01',
            'consent_date' => '2026-03-20',
        ]);

        $response->assertUnprocessable();
    }

    public function test_registration_fails_when_owner_is_not_a_community_member(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager);
        $outsider = User::factory()->create();
        $meterPoint = MeterPoint::factory()->for($outsider)->create();
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$community->id}/meter-points", [
            'meter_point_id' => $meterPoint->id,
            'from_date' => '2026-04-01',
            'consent_date' => '2026-03-20',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('meter_point_id');
    }

    public function test_registration_fails_when_consent_date_is_in_the_future(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager);
        $meterPoint = MeterPoint::factory()->for($manager)->create();
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$community->id}/meter-points", [
            'meter_point_id' => $meterPoint->id,
            'from_date' => '2026-04-01',
            'consent_date' => '2099-01-01',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('consent_date');
    }

    /**
     * BR-7 worked example straight from the assignment (section 7): MP-1
     * starts with an accepted, open-ended registration in community A from
     * 2026-01-01, and a deactivated one in B from 2024-03-01 to 2025-09-30.
     * Every case below re-starts from exactly that state.
     */
    private function seedWorkedExample(User $manager, MeterPoint $meterPoint, EnergyCommunity $communityA): void
    {
        EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $communityA->id,
            'meter_point_id' => $meterPoint->id,
            'state' => EnergyCommunityMeterPointState::Accepted,
            'from_date' => '2026-01-01',
            'to_date' => null,
            'consent_date' => '2025-12-20',
        ]);

        $communityB = EnergyCommunity::factory()->create(['state' => EnergyCommunityState::Activated]);
        $communityB->users()->attach($meterPoint->user_id, ['role' => CommunityRole::Member]);
        EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $communityB->id,
            'meter_point_id' => $meterPoint->id,
            'state' => EnergyCommunityMeterPointState::Deactivated,
            'from_date' => '2024-03-01',
            'to_date' => '2025-09-30',
            'consent_date' => '2024-02-20',
        ]);
    }

    public function test_br7_open_ended_accepted_registration_blocks_a_later_overlapping_one(): void
    {
        $manager = User::factory()->create();
        $meterPoint = MeterPoint::factory()->for($manager)->create();
        $communityA = EnergyCommunity::factory()->create(['state' => EnergyCommunityState::Activated]);
        $communityA->users()->attach($manager->id, ['role' => CommunityRole::Manager]);
        $this->seedWorkedExample($manager, $meterPoint, $communityA);

        $communityC = $this->communityWithManager($manager);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$communityC->id}/meter-points", [
            'meter_point_id' => $meterPoint->id,
            'from_date' => '2026-04-01',
            'consent_date' => '2026-03-20',
        ]);

        $response->assertStatus(409);
    }

    public function test_br7_deactivating_the_blocking_registration_allows_the_new_one(): void
    {
        $manager = User::factory()->create();
        $meterPoint = MeterPoint::factory()->for($manager)->create();
        $communityA = EnergyCommunity::factory()->create(['state' => EnergyCommunityState::Activated]);
        $communityA->users()->attach($manager->id, ['role' => CommunityRole::Manager]);
        $this->seedWorkedExample($manager, $meterPoint, $communityA);

        $registrationInA = EnergyCommunityMeterPoint::query()
            ->where('energy_community_id', $communityA->id)
            ->where('meter_point_id', $meterPoint->id)
            ->sole();

        Sanctum::actingAs($manager);
        $this->postJson("/api/registrations/{$registrationInA->id}/transition", [
            'state' => 'deactivated',
        ])->assertOk();

        $communityC = $this->communityWithManager($manager);

        $response = $this->postJson("/api/energy-communities/{$communityC->id}/meter-points", [
            'meter_point_id' => $meterPoint->id,
            'from_date' => '2026-04-01',
            'consent_date' => '2026-03-20',
        ]);

        $response->assertCreated();
    }

    public function test_br7_open_ended_registration_starting_earlier_still_overlaps(): void
    {
        $manager = User::factory()->create();
        $meterPoint = MeterPoint::factory()->for($manager)->create();
        $communityA = EnergyCommunity::factory()->create(['state' => EnergyCommunityState::Activated]);
        $communityA->users()->attach($manager->id, ['role' => CommunityRole::Manager]);
        $this->seedWorkedExample($manager, $meterPoint, $communityA);

        $communityC = $this->communityWithManager($manager);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$communityC->id}/meter-points", [
            'meter_point_id' => $meterPoint->id,
            'from_date' => '2025-01-01',
            'to_date' => null,
            'consent_date' => '2024-12-20',
        ]);

        $response->assertStatus(409);
    }

    public function test_br7_a_period_entirely_before_everything_blocking_does_not_overlap(): void
    {
        $manager = User::factory()->create();
        $meterPoint = MeterPoint::factory()->for($manager)->create();
        $communityA = EnergyCommunity::factory()->create(['state' => EnergyCommunityState::Activated]);
        $communityA->users()->attach($manager->id, ['role' => CommunityRole::Manager]);
        $this->seedWorkedExample($manager, $meterPoint, $communityA);

        $communityC = $this->communityWithManager($manager);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$communityC->id}/meter-points", [
            'meter_point_id' => $meterPoint->id,
            'from_date' => '2024-01-01',
            'to_date' => '2024-02-28',
            'consent_date' => '2023-12-20',
        ]);

        $response->assertCreated();
    }

    public function test_non_member_gets_404_listing_a_communitys_registrations(): void
    {
        $user = User::factory()->create();
        $community = EnergyCommunity::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/energy-communities/{$community->id}/meter-points");

        $response->assertNotFound();
    }

    public function test_manager_can_apply_a_legal_transition(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager);
        $registration = EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'state' => EnergyCommunityMeterPointState::New,
        ]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/registrations/{$registration->id}/transition", [
            'state' => 'requested',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.state', 'requested');
    }

    public function test_illegal_transition_is_rejected(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager);
        $registration = EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'state' => EnergyCommunityMeterPointState::New,
        ]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/registrations/{$registration->id}/transition", [
            'state' => 'accepted',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('state');
    }

    public function test_member_cannot_transition_a_registration(): void
    {
        $member = User::factory()->create();
        $community = EnergyCommunity::factory()->create();
        $community->users()->attach($member->id, ['role' => CommunityRole::Member]);
        $registration = EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'state' => EnergyCommunityMeterPointState::New,
        ]);
        Sanctum::actingAs($member);

        $response = $this->postJson("/api/registrations/{$registration->id}/transition", [
            'state' => 'requested',
        ]);

        $response->assertForbidden();
    }

    public function test_transition_to_error_requires_status_code(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager);
        $registration = EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'state' => EnergyCommunityMeterPointState::Requested,
        ]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/registrations/{$registration->id}/transition", [
            'state' => 'error',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('status_code');
    }

    public function test_transition_to_accepted_closes_the_period_when_deactivated(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager);
        $registration = EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'state' => EnergyCommunityMeterPointState::Accepted,
            'from_date' => '2026-01-01',
            'to_date' => null,
        ]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/registrations/{$registration->id}/transition", [
            'state' => 'deactivated',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.to_date', now()->toDateString());
    }

    public function test_delete_maps_new_registration_to_removed(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager);
        $registration = EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'state' => EnergyCommunityMeterPointState::New,
        ]);
        Sanctum::actingAs($manager);

        $response = $this->deleteJson("/api/registrations/{$registration->id}");

        $response->assertNoContent();
        $this->assertSame(
            EnergyCommunityMeterPointState::Removed,
            $registration->refresh()->state,
        );
    }

    public function test_delete_maps_accepted_registration_to_deactivated(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager);
        $registration = EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'state' => EnergyCommunityMeterPointState::Accepted,
        ]);
        Sanctum::actingAs($manager);

        $response = $this->deleteJson("/api/registrations/{$registration->id}");

        $response->assertNoContent();
        $this->assertSame(
            EnergyCommunityMeterPointState::Deactivated,
            $registration->refresh()->state,
        );
    }

    public function test_delete_fails_for_an_already_terminal_registration(): void
    {
        $manager = User::factory()->create();
        $community = $this->communityWithManager($manager);
        $registration = EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'state' => EnergyCommunityMeterPointState::Removed,
        ]);
        Sanctum::actingAs($manager);

        $response = $this->deleteJson("/api/registrations/{$registration->id}");

        $response->assertStatus(409);
    }
}
