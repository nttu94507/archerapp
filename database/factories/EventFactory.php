<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'     => 'Open ' . $this->faker->city(),
            'date'     => $this->faker->dateTimeBetween('-9 months', 'now'),
            'mode'     => $this->faker->randomElement(['indoor','outdoor']),
            'verified' => true,
            'level'    => $this->faker->randomElement(['local','regional','national']),
        ];
    }
}
