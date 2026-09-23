<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EnergyCommunityMeterPointState;
use App\Enums\EnergyDirection;
use App\Models\EnergyCommunity;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\MeterPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EcRegistrationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_for_an_unknown_ecid(): void
    {
        $this->artisan('ec:registrations', ['ecid' => 'DOES-NOT-EXIST'])
            ->assertExitCode(1)
            ->expectsOutputToContain('No energy community found');
    }

    public function test_it_reports_when_nothing_is_valid_on_the_given_day(): void
    {
        $community = EnergyCommunity::factory()->create(['ecid' => 'AT-TEST-001']);

        $this->artisan('ec:registrations', ['ecid' => 'AT-TEST-001', '--date' => '2026-06-01'])
            ->assertExitCode(0)
            ->expectsOutputToContain('No accepted registrations valid on 2026-06-01');
    }

    public function test_it_lists_only_accepted_and_valid_registrations_generation_first(): void
    {
        $community = EnergyCommunity::factory()->create(['ecid' => 'AT-TEST-002']);

        $consumption = MeterPoint::factory()->create(['energy_direction' => EnergyDirection::Consumption]);
        $generation = MeterPoint::factory()->create(['energy_direction' => EnergyDirection::Generation]);
        $expired = MeterPoint::factory()->create();
        $future = MeterPoint::factory()->create();
        $notAccepted = MeterPoint::factory()->create();

        // Valid on the query date, consumption — listed second.
        EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'meter_point_id' => $consumption->id,
            'state' => EnergyCommunityMeterPointState::Accepted,
            'from_date' => '2026-01-01',
            'to_date' => null,
        ]);

        // Valid on the query date, generation — listed first.
        EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'meter_point_id' => $generation->id,
            'state' => EnergyCommunityMeterPointState::Accepted,
            'from_date' => '2026-01-01',
            'to_date' => '2026-12-31',
        ]);

        // Accepted but already expired before the query date — excluded.
        EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'meter_point_id' => $expired->id,
            'state' => EnergyCommunityMeterPointState::Accepted,
            'from_date' => '2025-01-01',
            'to_date' => '2025-12-31',
        ]);

        // Accepted but not yet started on the query date — excluded.
        EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'meter_point_id' => $future->id,
            'state' => EnergyCommunityMeterPointState::Accepted,
            'from_date' => '2027-01-01',
            'to_date' => null,
        ]);

        // Valid period but not accepted — excluded.
        EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'meter_point_id' => $notAccepted->id,
            'state' => EnergyCommunityMeterPointState::New,
            'from_date' => '2026-01-01',
            'to_date' => null,
        ]);

        $this->artisan('ec:registrations', ['ecid' => 'AT-TEST-002', '--date' => '2026-06-15'])
            ->assertExitCode(0)
            ->expectsOutputToContain($generation->name)
            ->expectsOutputToContain($consumption->name)
            ->doesntExpectOutputToContain($expired->name)
            ->doesntExpectOutputToContain($future->name)
            ->doesntExpectOutputToContain($notAccepted->name);

        Artisan::call('ec:registrations', ['ecid' => 'AT-TEST-002', '--date' => '2026-06-15']);
        $output = Artisan::output();

        $this->assertLessThan(
            strpos($output, $consumption->name),
            strpos($output, $generation->name),
            'Expected the generation metering point to be listed before the consumption one.',
        );
    }

    public function test_it_defaults_to_today_without_a_date_option(): void
    {
        $community = EnergyCommunity::factory()->create(['ecid' => 'AT-TEST-003']);
        $meterPoint = MeterPoint::factory()->create(['energy_direction' => EnergyDirection::Generation]);
        EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'meter_point_id' => $meterPoint->id,
            'state' => EnergyCommunityMeterPointState::Accepted,
            'from_date' => now()->subDay()->toDateString(),
            'to_date' => null,
        ]);

        $this->artisan('ec:registrations', ['ecid' => 'AT-TEST-003'])
            ->assertExitCode(0)
            ->expectsOutputToContain($meterPoint->name);
    }
}
