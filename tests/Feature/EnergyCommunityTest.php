<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CommunityRole;
use App\Enums\EnergyCommunityState;
use App\Models\EnergyCommunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnergyCommunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_a_community(): void
    {
        $response = $this->postJson('/api/energy-communities', ['ecid' => 'AT001']);

        $response->assertUnauthorized();
    }

    public function test_creating_a_community_makes_the_creator_its_manager(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/energy-communities', [
            'ecid' => 'AT0070000902',
            'name' => 'Test Community',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.state', EnergyCommunityState::New->value);

        $community = EnergyCommunity::firstWhere('ecid', 'AT0070000902');
        $this->assertNotNull($community);
        $this->assertSame(
            CommunityRole::Manager,
            $community->users()->whereKey($user->id)->first()->membership->role,
        );
    }

    public function test_duplicate_ecid_is_rejected(): void
    {
        EnergyCommunity::factory()->create(['ecid' => 'AT0070000902']);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/energy-communities', ['ecid' => 'AT0070000902']);

        $response->assertUnprocessable()->assertJsonValidationErrors('ecid');
    }

    public function test_user_only_lists_communities_they_belong_to(): void
    {
        $user = User::factory()->create();
        $own = EnergyCommunity::factory()->create();
        $own->users()->attach($user->id, ['role' => CommunityRole::Member]);
        EnergyCommunity::factory()->create(); // not a member of this one
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/energy-communities');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($own->id, $response->json('data.0.id'));
    }

    public function test_admin_lists_all_communities(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        EnergyCommunity::factory()->count(3)->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/energy-communities');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_member_can_view_their_community(): void
    {
        $user = User::factory()->create();
        $community = EnergyCommunity::factory()->create();
        $community->users()->attach($user->id, ['role' => CommunityRole::Member]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/energy-communities/{$community->id}");

        $response->assertOk();
    }

    public function test_non_member_gets_404_for_a_community_they_do_not_belong_to(): void
    {
        $user = User::factory()->create();
        $community = EnergyCommunity::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/energy-communities/{$community->id}");

        $response->assertNotFound();
    }

    public function test_manager_can_add_a_user_to_the_community(): void
    {
        $manager = User::factory()->create();
        $newMember = User::factory()->create();
        $community = EnergyCommunity::factory()->create();
        $community->users()->attach($manager->id, ['role' => CommunityRole::Manager]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$community->id}/users", [
            'user_id' => $newMember->id,
            'role' => 'member',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('energy_community_user', [
            'energy_community_id' => $community->id,
            'user_id' => $newMember->id,
            'role' => 'member',
        ]);
    }

    public function test_member_cannot_add_a_user_to_the_community(): void
    {
        $member = User::factory()->create();
        $newMember = User::factory()->create();
        $community = EnergyCommunity::factory()->create();
        $community->users()->attach($member->id, ['role' => CommunityRole::Member]);
        Sanctum::actingAs($member);

        $response = $this->postJson("/api/energy-communities/{$community->id}/users", [
            'user_id' => $newMember->id,
            'role' => 'member',
        ]);

        $response->assertForbidden();
    }

    public function test_adding_the_same_user_twice_is_rejected(): void
    {
        $manager = User::factory()->create();
        $existingMember = User::factory()->create();
        $community = EnergyCommunity::factory()->create();
        $community->users()->attach($manager->id, ['role' => CommunityRole::Manager]);
        $community->users()->attach($existingMember->id, ['role' => CommunityRole::Member]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/energy-communities/{$community->id}/users", [
            'user_id' => $existingMember->id,
            'role' => 'manager',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }
}
