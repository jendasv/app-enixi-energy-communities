<?php

namespace Database\Factories;

use App\Models\GridOperator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GridOperator>
 */
class GridOperatorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Grid',
            'identifier' => 'AT'.fake()->unique()->numerify('######'),
        ];
    }
}
