<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_achievement_progress_for_user(): void
    {
        $user = User::factory()->create(['profile_completed_at' => now()]);

        // 建立 7 天有效紀錄，並累積超過 100 支箭
        foreach (range(0, 6) as $daysAgo) {
            $session = $user->archerySessions()->create([
                'bow_type' => 'recurve',
                'venue' => 'indoor',
                'distance_m' => 18,
                'arrows_total' => 18,
                'arrows_per_end' => 6,
                'target_face' => 'ten-ring',
                'score_total' => 120,
                'x_count' => 3,
                'm_count' => 1,
                'note' => 'test',
            ]);

            $session->timestamps = false;
            $session->created_at = now()->subDays($daysAgo);
            $session->updated_at = now()->subDays($daysAgo);
            $session->save();
        }

        $response = $this->actingAs($user)->get(route('achievements.index'));

        $response->assertOk();
        $response->assertSee('成就');
        $response->assertSee('連續 7 天');
        $response->assertSee('100 支箭');

        $this->assertDatabaseHas('achievement_definitions', [
            'key' => 'streak_7',
            'condition_type' => 'streak',
        ]);

        $this->assertDatabaseHas('user_achievement_progress', [
            'user_id' => $user->id,
            'current_value' => 7,
            'target_value' => 7,
        ]);
    }
}
