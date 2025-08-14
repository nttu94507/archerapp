<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Archer>
 */
class ArcherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bows = ['recurve','compound','barebow','longbow'];
        return [
            'name' => $this->faker->unique()->name(),
            'bow_type' => $this->faker->randomElement($bows),
        ];
    }
}
