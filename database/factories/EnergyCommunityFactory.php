<?php

namespace Database\Factories;

use App\Enums\EnergyCommunityState;
use App\Models\EnergyCommunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnergyCommunity>
 */
class EnergyCommunityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ecid' => 'AT'.fake()->unique()->numerify('####################'),
            'name' => fake()->words(3, true),
            'state' => EnergyCommunityState::New,
        ];
    }
}
