<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GridOperator;
use App\Models\MeterPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeterPointTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_register_a_meter_point(): void
    {
        $response = $this->postJson('/api/meter-points', []);

        $response->assertUnauthorized();
    }

    public function test_user_can_register_a_meter_point_with_a_known_grid_operator(): void
    {
        $user = User::factory()->create();
        $gridOperator = GridOperator::factory()->create(['identifier' => 'AT003000']);
        Sanctum::actingAs($user);

        $name = 'AT003000'.str_repeat('1', 25);

        $response = $this->postJson('/api/meter-points', [
            'name' => $name,
            'energy_direction' => 'generation',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('meter_points', [
            'name' => $name,
            'user_id' => $user->id,
            'grid_operator_id' => $gridOperator->identifier,
        ]);
    }

    public function test_registration_fails_when_no_grid_operator_matches_the_code(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/meter-points', [
            'name' => 'AT999999'.str_repeat('1', 25),
            'energy_direction' => 'generation',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_registration_fails_for_wrong_length_code(): void
    {
        $user = User::factory()->create();
        GridOperator::factory()->create(['identifier' => 'AT003000']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/meter-points', [
            'name' => 'AT003000TOOSHORT',
            'energy_direction' => 'generation',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_registration_fails_for_lowercase_characters(): void
    {
        $user = User::factory()->create();
        GridOperator::factory()->create(['identifier' => 'AT003000']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/meter-points', [
            'name' => 'AT003000'.str_repeat('a', 25),
            'energy_direction' => 'generation',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_registration_fails_for_duplicate_code(): void
    {
        $user = User::factory()->create();
        $gridOperator = GridOperator::factory()->create(['identifier' => 'AT003000']);
        $name = 'AT003000'.str_repeat('1', 25);
        MeterPoint::factory()->create(['name' => $name, 'grid_operator_id' => $gridOperator->identifier]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/meter-points', [
            'name' => $name,
            'energy_direction' => 'generation',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_user_only_sees_their_own_meter_points(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        MeterPoint::factory()->for($user)->create();
        MeterPoint::factory()->for($other)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/meter-points');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_sees_all_meter_points(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        MeterPoint::factory()->count(3)->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/meter-points');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_meter_points_are_filterable_by_energy_direction(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        MeterPoint::factory()->create(['energy_direction' => 'generation']);
        MeterPoint::factory()->create(['energy_direction' => 'consumption']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/meter-points?energy_direction=generation');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('generation', $response->json('data.0.energy_direction'));
    }
}
