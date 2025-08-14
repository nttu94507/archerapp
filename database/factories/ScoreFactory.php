<?php

namespace Database\Factories;

use App\Models\Archer;
use App\Models\Event;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Score>
 */
class ScoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 依 round 決定箭數與上限
        $round  = Round::inRandomOrder()->first() ?? Round::factory()->create();
        $arrows = $round->arrow_count;
        $max    = $round->max_score;

        // 模擬選手水準
        $aae = $this->faker->randomFloat(3, 6.5, 9.8); // 每箭平均 6.5~9.8
        $total = min($max, (int) round($aae * $arrows));

        $x = (int) round($total * 0.08 + $this->faker->numberBetween(0, 10));  // 粗略
        $ten = (int) round($total * 0.12 + $this->faker->numberBetween(0, 12)); // 粗略
        $stdev = $this->faker->randomFloat(3, 0.6, 2.2);

        $event = Event::inRandomOrder()->first() ?? Event::factory()->create();

        return [
            'archer_id'   => Archer::inRandomOrder()->value('id') ?? Archer::factory(),
            'event_id'    => $event->id,
            'round_id'    => $round->id,
            'total_score' => $total,
            'x_count'     => max(0, min($x, $arrows)),
            'ten_count'   => max(0, min($ten, $arrows)),
            'arrow_count' => $arrows,
            'stdev'       => $stdev,
            'scored_at'   => $event->date->copy()->setTime(rand(9,16), rand(0,59)),
        ];
    }
}
