<?php

namespace Database\Factories;

use App\Models\TeamPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamPost>
 */
class TeamPostFactory extends Factory
{
    protected $model = TeamPost::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => '尋找戰友 ' . $this->faker->word(),
            'content' => $this->faker->paragraph(),
            'contact' => $this->faker->phoneNumber(),
        ];
    }
}
