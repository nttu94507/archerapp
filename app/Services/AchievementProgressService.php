<?php

namespace App\Services;

use App\Models\AchievementDefinition;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AchievementProgressService
{
    private const MIN_ARROWS_FOR_ACTIVE_DAY = 12;

    /**
     * @return array<string,int>
     */
    public function syncForUser(User $user): array
    {
        $definitions = $this->seedDefinitions();
        $metrics = $this->buildMetrics($user);

        foreach ($definitions as $definition) {
            $currentValue = $definition->key === 'hidden_short_distance_specialist'
                ? ($metrics['short_distance_only_sessions'] ?? 0)
                : ($metrics[$definition->condition_type] ?? 0);
            $targetValue = max(1, (int) $definition->target_value);
            $progressPercent = min(100, (int) floor(($currentValue / $targetValue) * 100));

            $progress = $user->achievementProgress()->updateOrCreate(
                ['achievement_definition_id' => $definition->id],
                [
                    'target_value' => $targetValue,
                    'current_value' => $currentValue,
                    'progress_percent' => $progressPercent,
                    'last_calculated_at' => now(),
                ]
            );

            if ($currentValue >= $targetValue && $progress->unlocked_at === null) {
                $progress->forceFill(['unlocked_at' => now()])->save();
            }
        }

        return $metrics;
    }

    /**
     * @return Collection<int,AchievementDefinition>
     */
    private function seedDefinitions(): Collection
    {
        $items = [
            ['key' => 'streak_3', 'name' => '連續 3 天', 'description' => '連續 3 天完成射箭紀錄', 'title_name' => '三日弓手', 'category' => 'streak', 'condition_type' => 'streak', 'target_value' => 3, 'is_hidden' => false],
            ['key' => 'streak_7', 'name' => '連續 7 天', 'description' => '連續 7 天完成射箭紀錄', 'title_name' => '週訓行者', 'category' => 'streak', 'condition_type' => 'streak', 'target_value' => 7, 'is_hidden' => false],
            ['key' => 'streak_14', 'name' => '連續 14 天', 'description' => '連續 14 天完成射箭紀錄', 'title_name' => '百步定心', 'category' => 'streak', 'condition_type' => 'streak', 'target_value' => 14, 'is_hidden' => false],
            ['key' => 'days_7', 'name' => '累積 7 天', 'description' => '累積 7 天有有效訓練', 'title_name' => '穩定開弓', 'category' => 'total_days', 'condition_type' => 'total_days', 'target_value' => 7, 'is_hidden' => false],
            ['key' => 'days_30', 'name' => '累積 30 天', 'description' => '累積 30 天有有效訓練', 'title_name' => '月練成鋒', 'category' => 'total_days', 'condition_type' => 'total_days', 'target_value' => 30, 'is_hidden' => false],
            ['key' => 'days_100', 'name' => '累積 100 天', 'description' => '累積 100 天有有效訓練', 'title_name' => '百日宗師', 'category' => 'total_days', 'condition_type' => 'total_days', 'target_value' => 100, 'is_hidden' => false],
            ['key' => 'arrows_100', 'name' => '100 支箭', 'description' => '累積完成 100 支箭', 'title_name' => '百箭新秀', 'category' => 'total_arrows', 'condition_type' => 'total_arrows', 'target_value' => 100, 'is_hidden' => false],
            ['key' => 'arrows_1000', 'name' => '1000 支箭', 'description' => '累積完成 1000 支箭', 'title_name' => '千箭射手', 'category' => 'total_arrows', 'condition_type' => 'total_arrows', 'target_value' => 1000, 'is_hidden' => false],
            ['key' => 'arrows_5000', 'name' => '5000 支箭', 'description' => '累積完成 5000 支箭', 'title_name' => '萬里穿楊', 'category' => 'total_arrows', 'condition_type' => 'total_arrows', 'target_value' => 5000, 'is_hidden' => false],
            ['key' => 'hidden_short_distance_specialist', 'name' => '隱藏成就：十里坡傳說', 'description' => '在沒有任何一場超過 30 公尺的情況下，完成 100 場 30 公尺內計分。', 'title_name' => '十里坡箭神', 'category' => 'hidden', 'condition_type' => 'total_days', 'target_value' => 100, 'is_hidden' => true],
        ];

        return collect($items)->map(function (array $item) {
            return AchievementDefinition::query()->updateOrCreate(
                ['key' => $item['key']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'title_name' => $item['title_name'],
                    'category' => $item['category'],
                    'condition_type' => $item['condition_type'],
                    'target_value' => $item['target_value'],
                    'points' => 0,
                    'is_active' => true,
                    'is_hidden' => (bool) ($item['is_hidden'] ?? false),
                ]
            );
        });
    }

    /**
     * @return array<string,int>
     */
    private function buildMetrics(User $user): array
    {
        $activeDays = DB::table('archery_sessions')
            ->selectRaw('DATE(created_at) as active_date')
            ->where('user_id', $user->id)
            ->where('arrows_total', '>=', self::MIN_ARROWS_FOR_ACTIVE_DAY)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('active_date')
            ->pluck('active_date')
            ->map(fn ($date) => (string) $date)
            ->all();

        $totalDays = count($activeDays);
        $streak = $this->calculateCurrentStreak($activeDays);

        $totalArrows = (int) DB::table('archery_sessions')
            ->where('user_id', $user->id)
            ->sum('arrows_total');

        $overThirtyMetersCount = (int) DB::table('archery_sessions')
            ->where('user_id', $user->id)
            ->where('distance_m', '>', 30)
            ->count();

        $withinThirtyMetersCount = (int) DB::table('archery_sessions')
            ->where('user_id', $user->id)
            ->where('distance_m', '<=', 30)
            ->count();

        return [
            'streak' => $streak,
            'total_days' => $totalDays,
            'total_arrows' => $totalArrows,
            'short_distance_only_sessions' => $overThirtyMetersCount === 0 ? $withinThirtyMetersCount : 0,
        ];
    }

    /**
     * @param array<int,string> $activeDays
     */
    private function calculateCurrentStreak(array $activeDays): int
    {
        if ($activeDays === []) {
            return 0;
        }

        $lookup = array_fill_keys($activeDays, true);
        $cursor = now()->startOfDay();
        $streak = 0;

        while (isset($lookup[$cursor->toDateString()])) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }
}
