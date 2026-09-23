<?php

namespace Database\Seeders;

use App\Enums\CommunityRole;
use App\Enums\EnergyCommunityMeterPointState;
use App\Enums\EnergyCommunityState;
use App\Enums\EnergyDirection;
use App\Models\EnergyCommunity;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\GridOperator;
use App\Models\MeterPoint;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Fixed, known data — not random factories — so the credentials and IDs below
 * can be hardcoded into the Postman collection/environment and still work
 * right after a fresh `make install`.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const PASSWORD = 'password';

    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => self::PASSWORD,
            'is_admin' => true,
        ]);

        $manager = User::factory()->create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'password' => self::PASSWORD,
        ]);

        $member = User::factory()->create([
            'name' => 'Member',
            'email' => 'member@example.com',
            'password' => self::PASSWORD,
        ]);

        $owner = User::factory()->create([
            'name' => 'Meter Point Owner',
            'email' => 'owner@example.com',
            'password' => self::PASSWORD,
        ]);

        $operatorA = GridOperator::factory()->create([
            'name' => 'Netz Oberösterreich GmbH',
            'identifier' => 'AT003000',
        ]);

        $operatorB = GridOperator::factory()->create([
            'name' => 'Wien Energie Netze',
            'identifier' => 'AT004000',
        ]);

        $pvPlant = MeterPoint::factory()->create([
            'name' => 'AT003000'.str_pad('4711001', 25, '0', STR_PAD_LEFT),
            'user_id' => $owner->id,
            'energy_direction' => EnergyDirection::Generation,
            'grid_operator_id' => $operatorA->identifier,
        ]);

        $house = MeterPoint::factory()->create([
            'name' => 'AT003000'.str_pad('4711002', 25, '0', STR_PAD_LEFT),
            'user_id' => $owner->id,
            'energy_direction' => EnergyDirection::Consumption,
            'grid_operator_id' => $operatorA->identifier,
        ]);

        $managersGeneration = MeterPoint::factory()->create([
            'name' => 'AT004000'.str_pad('9001', 25, '0', STR_PAD_LEFT),
            'user_id' => $manager->id,
            'energy_direction' => EnergyDirection::Generation,
            'grid_operator_id' => $operatorB->identifier,
        ]);

        // Already activated — has an accepted generation registration (BR-12).
        $waldviertel = EnergyCommunity::factory()->create([
            'ecid' => 'AT00700009020GC999001000000000001',
            'name' => 'Energiegemeinschaft Waldviertel Nord',
            'state' => EnergyCommunityState::Activated,
        ]);
        $waldviertel->users()->attach($manager->id, ['role' => CommunityRole::Manager]);
        $waldviertel->users()->attach($owner->id, ['role' => CommunityRole::Member]);
        $waldviertel->users()->attach($member->id, ['role' => CommunityRole::Member]);

        EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $waldviertel->id,
            'meter_point_id' => $pvPlant->id,
            'state' => EnergyCommunityMeterPointState::Accepted,
            'from_date' => '2026-01-01',
            'to_date' => null,
            'consent_date' => '2025-12-20',
        ]);

        EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $waldviertel->id,
            'meter_point_id' => $house->id,
            'state' => EnergyCommunityMeterPointState::Requested,
            'from_date' => '2026-02-01',
            'to_date' => null,
            'consent_date' => '2026-01-15',
        ]);

        // Still `new` — not enough to activate yet, useful for exercising the
        // registration/transition/activate endpoints from a clean starting point.
        $sonnenweide = EnergyCommunity::factory()->create([
            'ecid' => 'AT00700009020GC999002000000000001',
            'name' => 'PV Sonnenweide',
            'state' => EnergyCommunityState::New,
        ]);
        $sonnenweide->users()->attach($manager->id, ['role' => CommunityRole::Manager]);

        EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $sonnenweide->id,
            'meter_point_id' => $managersGeneration->id,
            'state' => EnergyCommunityMeterPointState::New,
            'from_date' => '2026-03-01',
            'to_date' => null,
            'consent_date' => '2026-02-20',
        ]);

        // Rejected — useful for exercising the "can't register/activate a
        // rejected community" failure paths.
        $rejected = EnergyCommunity::factory()->create([
            'ecid' => 'AT00700009020GC999003000000000001',
            'name' => 'Rejected Example Community',
            'state' => EnergyCommunityState::Rejected,
        ]);
        $rejected->users()->attach($manager->id, ['role' => CommunityRole::Manager]);

        $this->command->info('Seeded users (password for all: "password"):');
        $this->command->table(['Role', 'Email'], [
            ['admin', $admin->email],
            ['manager (of Waldviertel Nord, PV Sonnenweide, Rejected Example)', $manager->email],
            ['member (of Waldviertel Nord)', $member->email],
            ['meter point owner (PV plant + house, both in Waldviertel Nord)', $owner->email],
        ]);
    }
}
