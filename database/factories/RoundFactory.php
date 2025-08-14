<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Round>
 */
class RoundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 兩個常見 round
        $rounds = [
            ['name'=>'WA 70m', 'distance'=>70, 'target_face'=>122, 'arrow_count'=>72, 'max_score'=>720],
            ['name'=>'WA 18m', 'distance'=>18, 'target_face'=>40,  'arrow_count'=>60, 'max_score'=>600],
        ];
        return $this->faker->randomElement($rounds);
    }
}
