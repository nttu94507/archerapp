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
     * @return array<string,float|int>
     */
    public function syncForUser(User $user): array
    {
        $definitions = $this->seedDefinitions();
        $metrics = $this->buildMetrics($user);

        foreach ($definitions as $definition) {
            $targetValue = max(1, (int) $definition->target_value);
            $currentValue = (float) ($metrics[$definition->condition_type] ?? 0);
            $progressPercent = min(100, (int) floor(($currentValue / $targetValue) * 100));

            $progress = $user->achievementProgress()->updateOrCreate(
                ['achievement_definition_id' => $definition->id],
                [
                    'target_value' => $targetValue,
                    'current_value' => (int) floor($currentValue),
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
        AchievementDefinition::query()->update(['is_active' => false]);

        return collect($this->definitionItems())->map(function (array $item) {
            return AchievementDefinition::query()->updateOrCreate(
                ['key' => $item['key']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'title_name' => $item['title_name'] ?? null,
                    'category' => $item['category'],
                    'condition_type' => $item['condition_type'],
                    'target_value' => $item['target_value'],
                    'points' => 0,
                    'is_active' => true,
                    'is_hidden' => false,
                ]
            );
        });
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function definitionItems(): array
    {
        return [
            // 核心成就 - 累積型
            ['key' => 'core_arrows_1000', 'name' => '累積 1,000 箭', 'description' => '累積射出 1,000 箭。', 'category' => 'core_accumulate', 'condition_type' => 'total_arrows', 'target_value' => 1000, 'title_name' => '新手射手'],
            ['key' => 'core_arrows_5000', 'name' => '累積 5,000 箭', 'description' => '累積射出 5,000 箭。', 'category' => 'core_accumulate', 'condition_type' => 'total_arrows', 'target_value' => 5000],
            ['key' => 'core_arrows_10000', 'name' => '累積 10,000 箭', 'description' => '累積射出 10,000 箭。', 'category' => 'core_accumulate', 'condition_type' => 'total_arrows', 'target_value' => 10000],
            ['key' => 'core_arrows_50000', 'name' => '累積 50,000 箭', 'description' => '累積射出 50,000 箭。', 'category' => 'core_accumulate', 'condition_type' => 'total_arrows', 'target_value' => 50000],
            ['key' => 'core_sessions_10', 'name' => '完成 10 場 session', 'description' => '累積完成 10 場訓練。', 'category' => 'core_accumulate', 'condition_type' => 'total_sessions', 'target_value' => 10],
            ['key' => 'core_sessions_50', 'name' => '完成 50 場', 'description' => '累積完成 50 場訓練。', 'category' => 'core_accumulate', 'condition_type' => 'total_sessions', 'target_value' => 50],
            ['key' => 'core_sessions_100', 'name' => '完成 100 場', 'description' => '累積完成 100 場訓練。', 'category' => 'core_accumulate', 'condition_type' => 'total_sessions', 'target_value' => 100],
            ['key' => 'core_sessions_300', 'name' => '完成 300 場', 'description' => '累積完成 300 場訓練。', 'category' => 'core_accumulate', 'condition_type' => 'total_sessions', 'target_value' => 300],
            ['key' => 'core_hours_50', 'name' => '累積 50 小時', 'description' => '估算累積訓練 50 小時。', 'category' => 'core_accumulate', 'condition_type' => 'estimated_hours', 'target_value' => 50],
            ['key' => 'core_hours_200', 'name' => '累積 200 小時', 'description' => '估算累積訓練 200 小時。', 'category' => 'core_accumulate', 'condition_type' => 'estimated_hours', 'target_value' => 200],
            ['key' => 'core_hours_500', 'name' => '累積 500 小時', 'description' => '估算累積訓練 500 小時。', 'category' => 'core_accumulate', 'condition_type' => 'estimated_hours', 'target_value' => 500],

            // 核心成就 - 成長型
            ['key' => 'core_aae_6', 'name' => 'AAE ≥ 6', 'description' => '整體 AAE 達到 6。', 'category' => 'core_growth', 'condition_type' => 'aae_x100', 'target_value' => 600, 'title_name' => '穩定射手'],
            ['key' => 'core_aae_7', 'name' => 'AAE ≥ 7', 'description' => '整體 AAE 達到 7。', 'category' => 'core_growth', 'condition_type' => 'aae_x100', 'target_value' => 700],
            ['key' => 'core_aae_8', 'name' => 'AAE ≥ 8', 'description' => '整體 AAE 達到 8。', 'category' => 'core_growth', 'condition_type' => 'aae_x100', 'target_value' => 800],
            ['key' => 'core_ten_rate_20', 'name' => '10分命中率 ≥ 20%', 'description' => '10 分命中率達 20%。', 'category' => 'core_growth', 'condition_type' => 'ten_rate_pct_x10', 'target_value' => 200],
            ['key' => 'core_ten_rate_30', 'name' => '10分命中率 ≥ 30%', 'description' => '10 分命中率達 30%。', 'category' => 'core_growth', 'condition_type' => 'ten_rate_pct_x10', 'target_value' => 300],
            ['key' => 'core_x_rate_10', 'name' => 'X命中率 ≥ 10%', 'description' => 'X 命中率達 10%。', 'category' => 'core_growth', 'condition_type' => 'x_rate_pct_x10', 'target_value' => 100],
            ['key' => 'core_best_90d', 'name' => 'Best(90d) 創新高', 'description' => '最近 90 天最佳單場創新高。', 'category' => 'core_growth', 'condition_type' => 'best_90d_new_high', 'target_value' => 1],
            ['key' => 'core_best_session_break', 'name' => '單場最高分突破', 'description' => '最新單場達到個人最高分。', 'category' => 'core_growth', 'condition_type' => 'latest_session_new_high', 'target_value' => 1],
            ['key' => 'core_sigma_drop_10', 'name' => 'σ 下降 10%', 'description' => '近期穩定度相對前期改善 10%。', 'category' => 'core_growth', 'condition_type' => 'sigma_improve_10pct', 'target_value' => 1],
            ['key' => 'core_sigma_target', 'name' => 'σ ≤ 目標值', 'description' => '整體 σ 達標（≤1.8）。', 'category' => 'core_growth', 'condition_type' => 'sigma_target_reached', 'target_value' => 1],

            // 核心成就 - 穩定型
            ['key' => 'core_streak_session_aae6_5', 'name' => '連續 5 場 AAE ≥ 6', 'description' => '連續 5 場 session 達成 AAE ≥ 6。', 'category' => 'core_stability', 'condition_type' => 'streak_aae6_sessions', 'target_value' => 5],
            ['key' => 'core_streak_session_aae7_10', 'name' => '連續 10 場 AAE ≥ 7', 'description' => '連續 10 場 session 達成 AAE ≥ 7。', 'category' => 'core_stability', 'condition_type' => 'streak_aae7_sessions', 'target_value' => 10],
            ['key' => 'core_streak_session_aae7_20', 'name' => '連續 20 場 AAE ≥ 7', 'description' => '連續 20 場 session 達成 AAE ≥ 7。', 'category' => 'core_stability', 'condition_type' => 'streak_aae7_sessions', 'target_value' => 20],
            ['key' => 'core_no_miss_single', 'name' => '單場無 MISS', 'description' => '任一場 session 無 miss。', 'category' => 'core_stability', 'condition_type' => 'single_no_miss_session', 'target_value' => 1],
            ['key' => 'core_no_miss_3', 'name' => '連續 3 場無 MISS', 'description' => '連續 3 場 session 無 miss。', 'category' => 'core_stability', 'condition_type' => 'streak_no_miss_sessions', 'target_value' => 3],
            ['key' => 'core_no_miss_10', 'name' => '連續 10 場無 MISS', 'description' => '連續 10 場 session 無 miss。', 'category' => 'core_stability', 'condition_type' => 'streak_no_miss_sessions', 'target_value' => 10],
            ['key' => 'core_arrow_8_10', 'name' => '連續 10 箭 ≥ 8分', 'description' => '連續 10 箭每箭至少 8 分。', 'category' => 'core_stability', 'condition_type' => 'streak_arrows_ge8', 'target_value' => 10],
            ['key' => 'core_arrow_7_30', 'name' => '連續 30 箭 ≥ 7分', 'description' => '連續 30 箭每箭至少 7 分。', 'category' => 'core_stability', 'condition_type' => 'streak_arrows_ge7', 'target_value' => 30],

            // 核心成就 - 習慣型
            ['key' => 'core_days_7', 'name' => '連續 7 天紀錄', 'description' => '連續 7 天有有效訓練紀錄。', 'category' => 'core_habit', 'condition_type' => 'streak_days', 'target_value' => 7],
            ['key' => 'core_days_30', 'name' => '連續 30 天', 'description' => '連續 30 天有有效訓練紀錄。', 'category' => 'core_habit', 'condition_type' => 'streak_days', 'target_value' => 30],
            ['key' => 'core_days_100', 'name' => '連續 100 天', 'description' => '連續 100 天有有效訓練紀錄。', 'category' => 'core_habit', 'condition_type' => 'streak_days', 'target_value' => 100],
            ['key' => 'core_weekly_2x_4w', 'name' => '每週 ≥ 2 次練習（連續 4 週）', 'description' => '連續 4 週每週至少 2 場。', 'category' => 'core_habit', 'condition_type' => 'streak_weeks_2x', 'target_value' => 4],
            ['key' => 'core_weekly_3x_8w', 'name' => '每週 ≥ 3 次（連續 8 週）', 'description' => '連續 8 週每週至少 3 場。', 'category' => 'core_habit', 'condition_type' => 'streak_weeks_3x', 'target_value' => 8],
            ['key' => 'core_monthly_10', 'name' => '每月 ≥ 10 場', 'description' => '單月完成至少 10 場。', 'category' => 'core_habit', 'condition_type' => 'best_monthly_sessions', 'target_value' => 10],
            ['key' => 'core_monthly_20', 'name' => '每月 ≥ 20 場', 'description' => '單月完成至少 20 場。', 'category' => 'core_habit', 'condition_type' => 'best_monthly_sessions', 'target_value' => 20],

            // 距離成就
            ['key' => 'distance_18_aae7', 'name' => '18m AAE ≥ 7', 'description' => '18m 平均單箭達 7。', 'category' => 'distance_18', 'condition_type' => 'aae_18_x100', 'target_value' => 700],
            ['key' => 'distance_18_ten30', 'name' => '18m 10分命中率 ≥ 30%', 'description' => '18m 10 分命中率達 30%。', 'category' => 'distance_18', 'condition_type' => 'ten_rate_18_x10', 'target_value' => 300],
            ['key' => 'distance_18_sessions30', 'name' => '18m 完成 30 場', 'description' => '18m 累積完成 30 場。', 'category' => 'distance_18', 'condition_type' => 'sessions_18', 'target_value' => 30],

            ['key' => 'distance_30_aae65', 'name' => '30m AAE ≥ 6.5', 'description' => '30m 平均單箭達 6.5。', 'category' => 'distance_30', 'condition_type' => 'aae_30_x100', 'target_value' => 650],
            ['key' => 'distance_30_stable10', 'name' => '30m 連續 10 場穩定', 'description' => '30m 連續 10 場 AAE ≥ 6.5。', 'category' => 'distance_30', 'condition_type' => 'streak_30_aae65', 'target_value' => 10],
            ['key' => 'distance_30_no_miss', 'name' => '30m 無 MISS 達成', 'description' => '30m 至少有 1 場無 miss。', 'category' => 'distance_30', 'condition_type' => 'single_30_no_miss', 'target_value' => 1],

            ['key' => 'distance_50_aae6', 'name' => '50m AAE ≥ 6', 'description' => '50m 平均單箭達 6。', 'category' => 'distance_50', 'condition_type' => 'aae_50_x100', 'target_value' => 600],
            ['key' => 'distance_50_x_target', 'name' => '50m X 命中達標', 'description' => '50m X 命中率達 5%。', 'category' => 'distance_50', 'condition_type' => 'x_rate_50_x10', 'target_value' => 50],
            ['key' => 'distance_50_stable', 'name' => '50m 穩定輸出', 'description' => '50m 連續 5 場 AAE ≥ 6。', 'category' => 'distance_50', 'condition_type' => 'streak_50_aae6', 'target_value' => 5],

            ['key' => 'distance_70_aae55', 'name' => '70m AAE ≥ 5.5', 'description' => '70m 平均單箭達 5.5。', 'category' => 'distance_70', 'condition_type' => 'aae_70_x100', 'target_value' => 550],
            ['key' => 'distance_70_sigma', 'name' => '70m 高穩定（σ達標）', 'description' => '70m σ 達標（≤2.2）。', 'category' => 'distance_70', 'condition_type' => 'sigma_70_target', 'target_value' => 1],
            ['key' => 'distance_70_maintain', 'name' => '70m 長期維持', 'description' => '70m 累積完成 20 場。', 'category' => 'distance_70', 'condition_type' => 'sessions_70', 'target_value' => 20],

            // 跨距離
            ['key' => 'cross_18_30_50', 'name' => '完成 18m → 30m → 50m', 'description' => '三個距離皆有完成紀錄。', 'category' => 'cross_distance', 'condition_type' => 'cross_18_30_50', 'target_value' => 1],
            ['key' => 'cross_three_targets', 'name' => '三距離達標', 'description' => '18m/30m/50m 核心門檻皆達標。', 'category' => 'cross_distance', 'condition_type' => 'cross_three_targets', 'target_value' => 1],
            ['key' => 'cross_all_stable', 'name' => '全距離穩定射手', 'description' => '18m/30m/50m/70m 均達穩定門檻。', 'category' => 'cross_distance', 'condition_type' => 'cross_all_stable', 'target_value' => 1],
            ['key' => 'cross_upgrade', 'name' => '距離升級（首次突破新距離）', 'description' => '首次挑戰超過 30m。', 'category' => 'cross_distance', 'condition_type' => 'distance_upgrade', 'target_value' => 1],

            // 等級成就
            ['key' => 'level_novice', 'name' => '🟤 新手射手（1000箭）', 'description' => '累積 1000 箭。', 'category' => 'level', 'condition_type' => 'total_arrows', 'target_value' => 1000, 'title_name' => '新手射手'],
            ['key' => 'level_stable', 'name' => '⚪ 穩定射手（AAE ≥ 6）', 'description' => 'AAE 達 6。', 'category' => 'level', 'condition_type' => 'aae_x100', 'target_value' => 600, 'title_name' => '穩定射手'],
            ['key' => 'level_advanced', 'name' => '🟡 進階射手（AAE ≥ 7 + 穩定）', 'description' => 'AAE ≥ 7 且連續穩定。', 'category' => 'level', 'condition_type' => 'level_advanced', 'target_value' => 1, 'title_name' => '進階射手'],
            ['key' => 'level_elite', 'name' => '🔥 菁英射手（AAE ≥ 8 + X率 + σ）', 'description' => 'AAE、X 率與穩定度三項達標。', 'category' => 'level', 'condition_type' => 'level_elite', 'target_value' => 1, 'title_name' => '菁英射手'],
        ];
    }

    /**
     * @return array<string,float|int>
     */
    private function buildMetrics(User $user): array
    {
        $totalArrows = (int) DB::table('archery_sessions')->where('user_id', $user->id)->sum('arrows_total');
        $totalSessions = (int) DB::table('archery_sessions')->where('user_id', $user->id)->count();
        $estimatedHours = (int) floor($totalArrows / 120);

        $activeDays = DB::table('archery_sessions')
            ->selectRaw('DATE(created_at) as active_date')
            ->where('user_id', $user->id)
            ->where('arrows_total', '>=', self::MIN_ARROWS_FOR_ACTIVE_DAY)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('active_date')
            ->pluck('active_date')
            ->map(fn ($date) => (string) $date)
            ->all();

        $streakDays = $this->calculateCurrentStreak($activeDays);
        $streakWeeks2 = $this->calculateWeekStreak($user->id, 2);
        $streakWeeks3 = $this->calculateWeekStreak($user->id, 3);
        $bestMonthlySessions = $this->bestMonthlySessions($user->id);

        $overall = DB::table('archery_shots as sh')
            ->join('archery_sessions as s', 's.id', '=', 'sh.session_id')
            ->where('s.user_id', $user->id)
            ->selectRaw('COUNT(*) as arrows, SUM(sh.score) as score_sum, SUM(CASE WHEN sh.score = 10 THEN 1 ELSE 0 END) as ten_hits, SUM(CASE WHEN sh.is_x = 1 THEN 1 ELSE 0 END) as x_hits, STDDEV_SAMP(sh.score) as sigma')
            ->first();

        $arrowsCount = max(1, (int) ($overall->arrows ?? 0));
        $aae = ((int) ($overall->score_sum ?? 0)) / $arrowsCount;
        $tenRate = ((int) ($overall->ten_hits ?? 0)) / $arrowsCount * 100;
        $xRate = ((int) ($overall->x_hits ?? 0)) / $arrowsCount * 100;
        $sigma = (float) ($overall->sigma ?? 0);

        $bestSessionScore = (int) DB::table('archery_sessions')->where('user_id', $user->id)->max('score_total');
        $latestSession = DB::table('archery_sessions')->where('user_id', $user->id)->orderByDesc('created_at')->orderByDesc('id')->first();
        $latestSessionNewHigh = ($latestSession && (int) $latestSession->score_total >= $bestSessionScore && $bestSessionScore > 0) ? 1 : 0;

        $best90 = (int) DB::table('archery_sessions')->where('user_id', $user->id)->where('created_at', '>=', now()->subDays(90))->max('score_total');
        $bestPrev90 = (int) DB::table('archery_sessions')->where('user_id', $user->id)->where('created_at', '<', now()->subDays(90))->max('score_total');
        $best90NewHigh = ($best90 > 0 && $best90 > $bestPrev90) ? 1 : 0;

        $sigmaRecent = (float) ($this->sigmaInWindow($user->id, now()->subDays(90), now()) ?? 0);
        $sigmaPrev = (float) ($this->sigmaInWindow($user->id, now()->subDays(180), now()->subDays(90)) ?? 0);
        $sigmaImprove10pct = ($sigmaPrev > 0 && $sigmaRecent > 0 && (($sigmaPrev - $sigmaRecent) / $sigmaPrev) >= 0.10) ? 1 : 0;
        $sigmaTargetReached = ($sigma > 0 && $sigma <= 1.8) ? 1 : 0;

        $sessionRows = DB::table('archery_sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'distance_m', 'arrows_total', 'score_total', 'm_count', 'created_at']);

        $streakAae6 = $this->sessionStreak($sessionRows, fn ($r) => $r->arrows_total > 0 && ($r->score_total / $r->arrows_total) >= 6);
        $streakAae7 = $this->sessionStreak($sessionRows, fn ($r) => $r->arrows_total > 0 && ($r->score_total / $r->arrows_total) >= 7);
        $streakNoMiss = $this->sessionStreak($sessionRows, fn ($r) => (int) $r->m_count === 0);
        $singleNoMiss = $sessionRows->contains(fn ($r) => (int) $r->m_count === 0) ? 1 : 0;

        $streak30Aae65 = $this->sessionStreak($sessionRows->where('distance_m', 30), fn ($r) => $r->arrows_total > 0 && ($r->score_total / $r->arrows_total) >= 6.5);
        $single30NoMiss = $sessionRows->where('distance_m', 30)->contains(fn ($r) => (int) $r->m_count === 0) ? 1 : 0;
        $streak50Aae6 = $this->sessionStreak($sessionRows->where('distance_m', 50), fn ($r) => $r->arrows_total > 0 && ($r->score_total / $r->arrows_total) >= 6);

        [$streakArrowsGe8, $streakArrowsGe7] = $this->shotScoreStreaks($user->id);

        $distanceStats = $this->distanceMetrics($user->id);

        $cross183050 = (($distanceStats['sessions_18'] ?? 0) > 0 && ($distanceStats['sessions_30'] ?? 0) > 0 && ($distanceStats['sessions_50'] ?? 0) > 0) ? 1 : 0;
        $crossThreeTargets = (($distanceStats['aae_18_x100'] ?? 0) >= 700 && ($distanceStats['aae_30_x100'] ?? 0) >= 650 && ($distanceStats['aae_50_x100'] ?? 0) >= 600) ? 1 : 0;
        $crossAllStable = ($crossThreeTargets === 1 && ($distanceStats['aae_70_x100'] ?? 0) >= 550) ? 1 : 0;
        $distanceUpgrade = $sessionRows->contains(fn ($r) => (int) $r->distance_m > 30) ? 1 : 0;

        $levelAdvanced = ($aae >= 7 && $streakAae7 >= 10) ? 1 : 0;
        $levelElite = ($aae >= 8 && $xRate >= 10 && $sigmaTargetReached === 1) ? 1 : 0;

        return array_merge([
            'total_arrows' => $totalArrows,
            'total_sessions' => $totalSessions,
            'estimated_hours' => $estimatedHours,
            'streak_days' => $streakDays,
            'streak_weeks_2x' => $streakWeeks2,
            'streak_weeks_3x' => $streakWeeks3,
            'best_monthly_sessions' => $bestMonthlySessions,
            'aae_x100' => (int) round($aae * 100),
            'ten_rate_pct_x10' => (int) round($tenRate * 10),
            'x_rate_pct_x10' => (int) round($xRate * 10),
            'best_90d_new_high' => $best90NewHigh,
            'latest_session_new_high' => $latestSessionNewHigh,
            'sigma_improve_10pct' => $sigmaImprove10pct,
            'sigma_target_reached' => $sigmaTargetReached,
            'streak_aae6_sessions' => $streakAae6,
            'streak_aae7_sessions' => $streakAae7,
            'single_no_miss_session' => $singleNoMiss,
            'streak_no_miss_sessions' => $streakNoMiss,
            'streak_arrows_ge8' => $streakArrowsGe8,
            'streak_arrows_ge7' => $streakArrowsGe7,
            'streak_30_aae65' => $streak30Aae65,
            'single_30_no_miss' => $single30NoMiss,
            'streak_50_aae6' => $streak50Aae6,
            'cross_18_30_50' => $cross183050,
            'cross_three_targets' => $crossThreeTargets,
            'cross_all_stable' => $crossAllStable,
            'distance_upgrade' => $distanceUpgrade,
            'level_advanced' => $levelAdvanced,
            'level_elite' => $levelElite,
        ], $distanceStats);
    }

    /** @return array{0:int,1:int} */
    private function shotScoreStreaks(int $userId): array
    {
        $shots = DB::table('archery_shots as sh')
            ->join('archery_sessions as s', 's.id', '=', 'sh.session_id')
            ->where('s.user_id', $userId)
            ->orderByDesc('s.created_at')
            ->orderByDesc('s.id')
            ->orderByDesc('sh.end_seq')
            ->orderByDesc('sh.shot_seq')
            ->get(['sh.score']);

        $streak8 = 0;
        foreach ($shots as $shot) {
            if ((int) $shot->score >= 8) {
                $streak8++;
                continue;
            }
            break;
        }

        $streak7 = 0;
        foreach ($shots as $shot) {
            if ((int) $shot->score >= 7) {
                $streak7++;
                continue;
            }
            break;
        }

        return [$streak8, $streak7];
    }

    private function sigmaInWindow(int $userId, $from, $to): ?float
    {
        $row = DB::table('archery_shots as sh')
            ->join('archery_sessions as s', 's.id', '=', 'sh.session_id')
            ->where('s.user_id', $userId)
            ->whereBetween('sh.created_at', [$from, $to])
            ->selectRaw('STDDEV_SAMP(sh.score) as sigma')
            ->first();

        return $row?->sigma;
    }

    /** @return array<string,int> */
    private function distanceMetrics(int $userId): array
    {
        $rows = DB::table('archery_sessions as s')
            ->leftJoin('archery_shots as sh', 'sh.session_id', '=', 's.id')
            ->where('s.user_id', $userId)
            ->whereIn('s.distance_m', [18, 30, 50, 70])
            ->groupBy('s.distance_m')
            ->selectRaw('s.distance_m, COUNT(DISTINCT s.id) as sessions, COUNT(sh.id) as arrows, SUM(sh.score) as score_sum, SUM(CASE WHEN sh.score = 10 THEN 1 ELSE 0 END) as ten_hits, SUM(CASE WHEN sh.is_x = 1 THEN 1 ELSE 0 END) as x_hits, STDDEV_SAMP(sh.score) as sigma')
            ->get();

        $result = [
            'sessions_18' => 0, 'sessions_30' => 0, 'sessions_50' => 0, 'sessions_70' => 0,
            'aae_18_x100' => 0, 'aae_30_x100' => 0, 'aae_50_x100' => 0, 'aae_70_x100' => 0,
            'ten_rate_18_x10' => 0, 'x_rate_50_x10' => 0,
            'sigma_70_target' => 0,
        ];

        foreach ($rows as $row) {
            $d = (int) $row->distance_m;
            $arrows = max(1, (int) ($row->arrows ?? 0));
            $result['sessions_' . $d] = (int) $row->sessions;
            $result['aae_' . $d . '_x100'] = (int) round((((int) ($row->score_sum ?? 0)) / $arrows) * 100);

            if ($d === 18) {
                $result['ten_rate_18_x10'] = (int) round((((int) ($row->ten_hits ?? 0)) / $arrows) * 1000);
            }

            if ($d === 50) {
                $result['x_rate_50_x10'] = (int) round((((int) ($row->x_hits ?? 0)) / $arrows) * 1000);
            }

            if ($d === 70) {
                $sigma = (float) ($row->sigma ?? 0);
                $result['sigma_70_target'] = ($sigma > 0 && $sigma <= 2.2) ? 1 : 0;
            }
        }

        return $result;
    }

    private function bestMonthlySessions(int $userId): int
    {
        $row = DB::table('archery_sessions')
            ->where('user_id', $userId)
            ->selectRaw("MAX(monthly_cnt) as max_monthly")
            ->fromSub(function ($q) use ($userId) {
                $q->from('archery_sessions')
                    ->where('user_id', $userId)
                    ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as monthly_cnt")
                    ->groupBy('ym');
            }, 'm')
            ->first();

        return (int) ($row->max_monthly ?? 0);
    }

    private function calculateWeekStreak(int $userId, int $minPerWeek): int
    {
        $weeks = DB::table('archery_sessions')
            ->where('user_id', $userId)
            ->selectRaw("YEARWEEK(created_at, 3) as yw, COUNT(*) as cnt")
            ->groupBy('yw')
            ->orderByDesc('yw')
            ->get();

        $lookup = [];
        foreach ($weeks as $w) {
            $lookup[(int) $w->yw] = (int) $w->cnt;
        }

        $cursor = (int) now()->isoWeekYear() * 100 + (int) now()->isoWeek();
        $streak = 0;

        for ($i = 0; $i < 104; $i++) {
            if (($lookup[$cursor] ?? 0) >= $minPerWeek) {
                $streak++;
                $cursor = (int) now()->subWeeks($streak)->isoWeekYear() * 100 + (int) now()->subWeeks($streak)->isoWeek();
                continue;
            }
            break;
        }

        return $streak;
    }

    /**
     * @param Collection<int,mixed> $rows
     */
    private function sessionStreak(Collection $rows, callable $predicate): int
    {
        $streak = 0;
        foreach ($rows as $row) {
            if ($predicate($row)) {
                $streak++;
                continue;
            }
            break;
        }

        return $streak;
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
