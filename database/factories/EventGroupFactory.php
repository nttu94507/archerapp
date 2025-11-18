<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventGroup>
 */
class EventGroupFactory extends Factory
{
    protected $model = EventGroup::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => $this->faker->randomElement(['男子反曲弓', '女子反曲弓', '公開複合弓']) . ' ' . $this->faker->numberBetween(30, 70) . 'm',
            'bow_type' => $this->faker->randomElement(['recurve', 'compound', 'barebow']),
            'gender' => $this->faker->randomElement(['male', 'female', 'open']),
            'age_class' => $this->faker->randomElement(['U15', 'U18', 'Open']),
            'distance' => $this->faker->randomElement(['30m', '50m', '70m']),
            'quota' => $this->faker->numberBetween(8, 64),
            'fee' => $this->faker->numberBetween(800, 2500),
            'is_team' => $this->faker->boolean(),
        ];
    }
}
