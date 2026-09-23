<?php

namespace Database\Factories;

use App\Enums\EnergyDirection;
use App\Models\GridOperator;
use App\Models\MeterPoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeterPoint>
 */
class MeterPointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gridOperator = GridOperator::factory()->create();

        return [
            'name' => $gridOperator->identifier.$this->randomCodeSuffix(),
            'user_id' => User::factory(),
            'energy_direction' => fake()->randomElement(EnergyDirection::cases())->value,
            'grid_operator_id' => $gridOperator->identifier,
        ];
    }

    /**
     * The 25 characters after the grid operator's 8-character prefix —
     * uppercase letters and digits only, per BR-1.
     */
    private function randomCodeSuffix(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        return collect(range(1, 25))
            ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
            ->implode('');
    }
}
