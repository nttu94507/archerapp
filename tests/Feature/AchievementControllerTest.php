<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_only_next_target_in_each_achievement_series(): void
    {
        $user = User::factory()->create(['profile_completed_at' => now()]);

        // 建立 7 天有效紀錄，總箭數為 126。
        // 預期：箭數系列從 10000 起算，進行中顯示 10000，不顯示 30000。
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

        // streak 系列：7 已達成，進行中顯示 14，不再顯示 3
        $response->assertSee('連續 14 天');
        $response->assertDontSee('連續 3 天完成射箭紀錄');

        // arrows 系列：只保留 1/3/6/9 萬 + 10 萬，下一個是 10000
        $response->assertSee('10000 支箭');
        $response->assertDontSee('30000 支箭');

        // sessions 系列：只有 7 局，應看到 100 局，不會直接出現 500 局
        $response->assertSee('100 局');
        $response->assertDontSee('500 局');

        // 5 年長期成就（1825 天）在天數系列中可見
        $response->assertSee('五年如一日');

        $this->assertDatabaseHas('achievement_definitions', [
            'key' => 'arrows_10000',
            'condition_type' => 'total_arrows',
            'title_name' => '千矢貫心',
        ]);

        $this->assertDatabaseMissing('achievement_definitions', [
            'key' => 'arrows_5000',
        ]);

        $this->assertDatabaseHas('achievement_definitions', [
            'key' => 'arrows_100000',
            'name' => '草船借箭',
            'title_name' => '草船借箭',
        ]);

        $this->assertDatabaseHas('user_achievement_progress', [
            'user_id' => $user->id,
            'target_value' => 100,
            'current_value' => 126,
        ]);
    }

    public function test_it_unlocks_hidden_title_for_short_distance_only_users(): void
    {
        $user = User::factory()->create(['profile_completed_at' => now()]);

        foreach (range(1, 100) as $index) {
            $session = $user->archerySessions()->create([
                'bow_type' => 'recurve',
                'venue' => 'indoor',
                'distance_m' => 30,
                'arrows_total' => 12,
                'arrows_per_end' => 6,
                'target_face' => 'ten-ring',
                'score_total' => 90,
                'x_count' => 1,
                'm_count' => 0,
                'note' => 'hidden-achievement-test',
            ]);

            $session->timestamps = false;
            $session->created_at = now()->subDays($index);
            $session->updated_at = now()->subDays($index);
            $session->save();
        }

        $response = $this->actingAs($user)->get(route('achievements.index'));

        $response->assertOk();
        $response->assertSee('十里坡箭神');
        $response->assertSee('隱藏成就：十里坡傳說');

        $this->assertDatabaseHas('user_achievement_progress', [
            'user_id' => $user->id,
            'current_value' => 100,
            'target_value' => 100,
        ]);
    }

    public function test_it_unlocks_session_achievement_by_total_sessions(): void
    {
        $user = User::factory()->create(['profile_completed_at' => now()]);

        foreach (range(1, 100) as $index) {
            $session = $user->archerySessions()->create([
                'bow_type' => 'recurve',
                'venue' => 'indoor',
                'distance_m' => 18,
                'arrows_total' => 6,
                'arrows_per_end' => 6,
                'target_face' => 'ten-ring',
                'score_total' => 45,
                'x_count' => 0,
                'm_count' => 0,
                'note' => 'session-series-test',
            ]);

            $session->timestamps = false;
            $session->created_at = now()->subDays($index);
            $session->updated_at = now()->subDays($index);
            $session->save();
        }

        $response = $this->actingAs($user)->get(route('achievements.index'));

        $response->assertOk();
        $response->assertSee('100 局');
        $response->assertSee('解鎖稱號：百局穩手');

        $this->assertDatabaseHas('user_achievement_progress', [
            'user_id' => $user->id,
            'current_value' => 100,
            'target_value' => 100,
        ]);
    }
}
