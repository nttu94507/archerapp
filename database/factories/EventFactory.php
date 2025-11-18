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
        $start = $this->faker->dateTimeBetween('-1 month', '+1 month');
        $end   = (clone $start)->modify('+' . $this->faker->numberBetween(0, 2) . ' days');

        return [
            'name'       => 'Open ' . $this->faker->city(),
            'start_date' => $start->format('Y-m-d'),
            'end_date'   => $end->format('Y-m-d'),
            'mode'       => $this->faker->randomElement(['indoor','outdoor']),
            'verified'   => true,
            'level'      => $this->faker->randomElement(['local','regional','national']),
            'organizer'  => $this->faker->company(),
            'reg_start'  => $this->faker->optional()->dateTimeBetween('-2 months', 'now'),
            'reg_end'    => $this->faker->optional()->dateTimeBetween('now', '+2 months'),
            'venue'      => $this->faker->optional()->streetAddress(),
            'map_link'   => $this->faker->optional()->url(),
        ];
    }
}
