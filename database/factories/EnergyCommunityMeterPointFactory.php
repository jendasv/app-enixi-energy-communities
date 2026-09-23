<?php

namespace Database\Factories;

use App\Enums\EnergyCommunityMeterPointState;
use App\Models\EnergyCommunity;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\MeterPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnergyCommunityMeterPoint>
 */
class EnergyCommunityMeterPointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'energy_community_id' => EnergyCommunity::factory(),
            'meter_point_id' => MeterPoint::factory(),
            'state' => EnergyCommunityMeterPointState::Accepted,
            'from_date' => fake()->dateTimeBetween('-1 year', '-1 month')->format('Y-m-d'),
            'to_date' => null,
            'consent_date' => fake()->dateTimeBetween('-1 year', '-1 month')->format('Y-m-d'),
        ];
    }
}
