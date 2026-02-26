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
            $currentValue = $metrics[$definition->condition_type] ?? 0;
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
            ['key' => 'streak_3', 'name' => '連續 3 天', 'description' => '連續 3 天完成射箭紀錄', 'category' => 'streak', 'condition_type' => 'streak', 'target_value' => 3],
            ['key' => 'streak_7', 'name' => '連續 7 天', 'description' => '連續 7 天完成射箭紀錄', 'category' => 'streak', 'condition_type' => 'streak', 'target_value' => 7],
            ['key' => 'streak_14', 'name' => '連續 14 天', 'description' => '連續 14 天完成射箭紀錄', 'category' => 'streak', 'condition_type' => 'streak', 'target_value' => 14],
            ['key' => 'days_7', 'name' => '累積 7 天', 'description' => '累積 7 天有有效訓練', 'category' => 'total_days', 'condition_type' => 'total_days', 'target_value' => 7],
            ['key' => 'days_30', 'name' => '累積 30 天', 'description' => '累積 30 天有有效訓練', 'category' => 'total_days', 'condition_type' => 'total_days', 'target_value' => 30],
            ['key' => 'days_100', 'name' => '累積 100 天', 'description' => '累積 100 天有有效訓練', 'category' => 'total_days', 'condition_type' => 'total_days', 'target_value' => 100],
            ['key' => 'arrows_100', 'name' => '100 支箭', 'description' => '累積完成 100 支箭', 'category' => 'total_arrows', 'condition_type' => 'total_arrows', 'target_value' => 100],
            ['key' => 'arrows_1000', 'name' => '1000 支箭', 'description' => '累積完成 1000 支箭', 'category' => 'total_arrows', 'condition_type' => 'total_arrows', 'target_value' => 1000],
            ['key' => 'arrows_5000', 'name' => '5000 支箭', 'description' => '累積完成 5000 支箭', 'category' => 'total_arrows', 'condition_type' => 'total_arrows', 'target_value' => 5000],
        ];

        return collect($items)->map(function (array $item) {
            return AchievementDefinition::query()->firstOrCreate(
                ['key' => $item['key']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'category' => $item['category'],
                    'condition_type' => $item['condition_type'],
                    'target_value' => $item['target_value'],
                    'points' => 0,
                    'is_active' => true,
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

        return [
            'streak' => $streak,
            'total_days' => $totalDays,
            'total_arrows' => $totalArrows,
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
