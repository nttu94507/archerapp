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
            'name'       => 'Open ' . $this->faker->city(),
            // 只存日期，不含時間，適合 date 型態
            'date'       => $this->faker->date('Y-m-d', 'now'),
            'mode'       => $this->faker->randomElement(['indoor','outdoor']),
            'verified'   => true,
            'level'      => $this->faker->randomElement(['local','regional','national']),
            // 補上 organizer，避免 1364 錯誤
            'organizer'  => $this->faker->company(),
            // 可選填，沒有可以留 null
            'reg_start'  => $this->faker->optional()->dateTimeBetween('-2 months', 'now'),
            'reg_end'    => $this->faker->optional()->dateTimeBetween('now', '+2 months'),
            'venue'      => $this->faker->optional()->streetAddress(),
            'map_link'   => $this->faker->optional()->url(),
        ];
    }
}
