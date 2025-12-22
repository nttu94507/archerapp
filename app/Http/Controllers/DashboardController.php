<?php

namespace App\Http\Controllers;

use App\Models\ArcherySession;
use App\Models\ArcheryShot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class DashboardController extends Controller
{

    public function index()
    {
        $userId = auth()->id();

        [$cmStart, $cmEnd] = $this->monthWindow('current');
        [$pmStart, $pmEnd] = $this->monthWindow('prev');

        $curr = $this->monthAgg($cmStart, $cmEnd);
        $prev = $this->monthAgg($pmStart, $pmEnd);

        // 月結指標打包
        $monthlyIndex = [
            // 量：用「% 變化」
            'arrows' => ['label' => '練習量（箭）', 'cur' => $curr['arrows'], 'prev' => $prev['arrows'], 'mode' => 'pct'],

            'sigma' => ['label' => 'σ（穩定度）', 'cur' => $curr['sigma'], 'prev' => $prev['sigma'], 'mode' => 'abs', 'invert' => true, 'fmt' => 3],
//            'hours' => ['label' => '總時長（h）', 'cur' => $curr['hours'], 'prev' => $prev['hours'], 'mode' => 'pct', 'fmt' => 1],

            // 品質：AAE、X%、10%（顯示「百分點」/ 同時附上 % 變化）
            'aae' => ['label' => '單箭均分', 'cur' => $curr['aae'], 'prev' => $prev['aae'], 'mode' => 'both', 'fmt' => 2],
            'x_rate' => ['label' => 'X 命中率', 'cur' => $curr['x_rate'], 'prev' => $prev['x_rate'], 'mode' => 'pp'],   // 百分點
            'ten_rate' => ['label' => '10 命中率', 'cur' => $curr['ten_rate'], 'prev' => $prev['ten_rate'], 'mode' => 'pp'],
            'active_days' => ['label' => '本月練習', 'cur' => $curr['active_days'], 'prev' => $prev['active_days'], 'mode' => 'pct'],

            // 穩定度：σ 越低越好 → 良性方向相反（invert）

        ];

        // 其餘你原本塞給 view 的資料...
        return view('dashboard.index', [
            'monthlyIndex' => $monthlyIndex,
            ...$this->buildDashboardData($userId, $curr),
        ]);
    }

    private function buildDashboardData(?int $userId, array $currMonthly): array
    {
        if (!$userId) {
            return [
                'stats' => [],
                'weeklyTrend' => [],
                'recentSessions' => [],
                'goals' => [],
                'notes' => [],
                'badges' => [],
            ];
        }

        $sessionQuery = ArcherySession::query()->where('user_id', $userId);
        $shotQuery = ArcheryShot::query()->whereHas('session', fn ($q) => $q->where('user_id', $userId));

        $firstSession = (clone $sessionQuery)->orderBy('created_at')->first();
        $lastSession = (clone $sessionQuery)->latest()->first();

        $shotAgg = (clone $shotQuery)
            ->selectRaw('
                COUNT(*) AS arrows,
                SUM(score) AS score_sum,
                SUM(CASE WHEN score >= 9 THEN 1 ELSE 0 END) AS gold_cnt,
                SUM(CASE WHEN score BETWEEN 7 AND 8 THEN 1 ELSE 0 END) AS red_cnt,
                STDDEV_SAMP(score) AS sigma
            ')
            ->first();

        $arrowsTotal = (int) ($shotAgg->arrows ?? 0);
        $scoreTotal = (int) ($shotAgg->score_sum ?? 0);
        $goldRate = $arrowsTotal > 0 ? (($shotAgg->gold_cnt ?? 0) / $arrowsTotal) : null;
        $redRate = $arrowsTotal > 0 ? (($shotAgg->red_cnt ?? 0) / $arrowsTotal) : null;
        $avgScore = $arrowsTotal > 0 ? $scoreTotal / $arrowsTotal : null;

        $bestEnd = (clone $shotQuery)
            ->selectRaw('SUM(score) AS end_total')
            ->groupBy('session_id', 'end_seq')
            ->orderByDesc('end_total')
            ->value('end_total');

        $bestThirtySix = (clone $sessionQuery)
            ->where('arrows_total', '>=', 36)
            ->orderByDesc('score_total')
            ->value('score_total');

        $stats = [
            'first_session_at' => optional($firstSession?->created_at)?->format('Y/m/d'),
            'days_since_start' => $firstSession?->created_at?->startOfDay()->diffInDays(now()->startOfDay()) + 1,
            'active_days_this_month' => $currMonthly['active_days'] ?? null,
            'arrows_this_month' => $currMonthly['arrows'] ?? null,
            'hours_this_month' => null,
            'avg_score_per_arrow' => $avgScore,
            'streak_days' => $this->computeStreak($sessionQuery),
            'best_end' => $bestEnd ?: null,
            'best_36' => $bestThirtySix ?: null,
            'gold_rate' => $goldRate,
            'red_rate' => $redRate,
            'last_active' => $lastSession?->created_at?->diffForHumans() ?? '—',
        ];

        $weeklyTrend = $this->weeklyTrend($userId);
        $recentSessions = $this->recentSessions($sessionQuery);
        $notes = $this->extractNotes($sessionQuery);
        $goals = $this->mockGoals($stats);
        $badges = $this->deriveBadges($stats);

        $insights = $this->deriveInsights($weeklyTrend, $stats);
        $heroStats = $this->heroStats($stats, $weeklyTrend);
        $sparks = [
            'arrows' => array_map(fn ($w) => $w['arrows'] ?? 0, $weeklyTrend),
            'avg' => array_map(fn ($w) => $w['avg'] ?? null, $weeklyTrend),
            'sigma' => array_map(fn ($w) => $w['sigma'] ?? null, $weeklyTrend),
        ];

        return compact('stats', 'weeklyTrend', 'recentSessions', 'goals', 'notes', 'badges', 'insights', 'heroStats', 'sparks');
    }

    private function computeStreak(\Illuminate\Database\Eloquent\Builder $sessionQuery): int
    {
        $dates = $sessionQuery
            ->orderByDesc('created_at')
            ->pluck('created_at')
            ->map(fn ($d) => $d->toDateString())
            ->unique();

        $streak = 0;
        $cursor = now()->startOfDay();
        foreach ($dates as $date) {
            if ($date === $cursor->toDateString()) {
                $streak++;
                $cursor->subDay();
            } elseif ($date < $cursor->toDateString()) {
                break;
            }
        }

        return $streak;
    }

    private function weeklyTrend(int $userId, int $weeks = 8): array
    {
        $result = [];
        $start = now()->startOfWeek()->subWeeks($weeks - 1);

        for ($i = 0; $i < $weeks; $i++) {
            $from = (clone $start)->addWeeks($i);
            $to = (clone $from)->endOfWeek();

            $agg = ArcheryShot::query()
                ->whereHas('session', fn ($q) => $q
                    ->where('user_id', $userId)
                    ->whereBetween('created_at', [$from, $to])
                )
                ->selectRaw('
                    COUNT(*) AS arrows,
                    SUM(score) AS score_sum,
                    SUM(CASE WHEN is_x = 1 AND score = 10 THEN 1 ELSE 0 END) AS x_cnt,
                    STDDEV_SAMP(score) AS sigma
                ')
                ->first();

            $arrows = (int) ($agg->arrows ?? 0);
            $scoreSum = (int) ($agg->score_sum ?? 0);
            $xCnt = (int) ($agg->x_cnt ?? 0);
            $sigma = is_null($agg->sigma) ? null : (float) $agg->sigma;

            $result[] = [
                'week' => 'W' . $from->isoWeek(),
                'range' => $from->format('m/d') . ' - ' . $to->format('m/d'),
                'arrows' => $arrows,
                'avg' => $arrows > 0 ? round($scoreSum / $arrows, 2) : null,
                'sigma' => is_null($sigma) ? null : round($sigma, 2),
                'x_rate' => $arrows > 0 ? round($xCnt / $arrows * 100, 1) : null,
            ];
        }

        return $result;
    }

    private function recentSessions($sessionQuery, int $limit = 4): array
    {
        return $sessionQuery
            ->latest()
            ->take($limit)
            ->get()
            ->map(function ($session) {
                $arrows = $session->arrows_total ?? $session->shots()->count();
                $scoreSum = $session->score_total ?? $session->shots()->sum('score');
                $avg = $arrows > 0 ? round($scoreSum / $arrows, 2) : null;

                $venue = match ($session->venue) {
                    'indoor' => '室內',
                    'outdoor' => '室外',
                    default => '—',
                };

                return [
                    'date' => $session->created_at?->format('Y/m/d') ?? '—',
                    'location' => trim($venue . ' ' . ($session->distance_m ? $session->distance_m . 'm' : '')),
                    'arrows' => $arrows ?: 0,
                    'avg' => $avg,
                    'score' => $scoreSum ?: null,
                    'wind' => '—',
                    'notes' => $session->note ?: '—',
                ];
            })
            ->all();
    }

    private function extractNotes($sessionQuery, int $limit = 3): array
    {
        return $sessionQuery
            ->whereNotNull('note')
            ->where('note', '!=', '')
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn ($s) => ['tag' => $s->bow_type ?? '訓練', 'text' => $s->note])
            ->all();
    }

    private function mockGoals(array $stats): array
    {
        $streak = (int) ($stats['streak_days'] ?? 0);
        $goldRate = $stats['gold_rate'] ?? null;
        $best36 = $stats['best_36'] ?? null;

        return [
            [
                'title' => '36 箭 ≥ 330',
                'progress' => $best36 ? min(1, $best36 / 330) : 0,
                'due' => '—',
            ],
            [
                'title' => '連續訓練 14 天',
                'progress' => $streak ? min(1, $streak / 14) : 0,
                'due' => '—',
            ],
            [
                'title' => 'X% / Gold 率 ≥ 38%',
                'progress' => $goldRate !== null ? min(1, $goldRate / 0.38) : 0,
                'due' => '—',
            ],
        ];
    }

    private function deriveBadges(array $stats): array
    {
        $badges = [];

        if (($stats['streak_days'] ?? 0) >= 7) {
            $badges[] = ['icon' => '🔥', 'title' => '7-Day Streak'];
        }
        if (($stats['arrows_this_month'] ?? 0) >= 1000) {
            $badges[] = ['icon' => '🎯', 'title' => '本月 1000 Arrows'];
        }
        if (!empty($stats['best_end'])) {
            $badges[] = ['icon' => '🏆', 'title' => '最佳單趟 ' . $stats['best_end']];
        }

        return $badges;
    }

    private function deriveInsights(array $weeklyTrend, array $stats): array
    {
        if (empty($weeklyTrend)) {
            return [];
        }

        $latest = end($weeklyTrend) ?: [];
        $prev = count($weeklyTrend) > 1 ? $weeklyTrend[count($weeklyTrend) - 2] : [];

        $insights = [];

        if (($latest['arrows'] ?? 0) > 0) {
            $deltaArrows = ($latest['arrows'] ?? 0) - ($prev['arrows'] ?? 0);
            $trendText = $deltaArrows === 0
                ? '訓練量與上週相近'
                : (($deltaArrows > 0 ? '增加 ' : '減少 ') . abs($deltaArrows) . ' 支箭');
            $insights[] = [
                'title' => '訓練量',
                'value' => $latest['arrows'] ?? 0,
                'hint' => $trendText,
            ];
        }

        if (!is_null($latest['avg'] ?? null)) {
            $deltaAvg = ($latest['avg'] ?? 0) - ($prev['avg'] ?? 0);
            $trendText = $deltaAvg === 0
                ? '平均分與上週持平'
                : (($deltaAvg > 0 ? '提升 ' : '下降 ') . number_format(abs($deltaAvg), 2) . ' 分');
            $insights[] = [
                'title' => '單箭分數',
                'value' => $latest['avg'],
                'hint' => $trendText,
            ];
        }

        if (!is_null($latest['sigma'] ?? null)) {
            $deltaSigma = ($latest['sigma'] ?? 0) - ($prev['sigma'] ?? 0);
            $trendText = $deltaSigma === 0
                ? '穩定度與上週一致'
                : (($deltaSigma < 0 ? '更穩定 ' : '波動增加 ') . number_format(abs($deltaSigma), 2));
            $insights[] = [
                'title' => '穩定度 σ',
                'value' => $latest['sigma'],
                'hint' => $trendText,
            ];
        }

        if (($stats['streak_days'] ?? 0) > 0) {
            $insights[] = [
                'title' => '連續天數',
                'value' => $stats['streak_days'],
                'hint' => '持續累積紀律',
            ];
        }

        return $insights;
    }

    private function heroStats(array $stats, array $weeklyTrend): array
    {
        $latestWeek = end($weeklyTrend) ?: [];
        $arrowsWeek = $latestWeek['arrows'] ?? 0;

        $activeDays = $stats['active_days_this_month'] ?? 0;
        $avgArrowsPerDay = $activeDays > 0 ? round(($stats['arrows_this_month'] ?? 0) / $activeDays) : null;

        return [
            [
                'label' => 'AAE 全期平均',
                'value' => $stats['avg_score_per_arrow'] ?? null,
                'suffix' => '分',
                'hint' => '全部訓練平均單箭分',
            ],
            [
                'label' => '本月箭數',
                'value' => $stats['arrows_this_month'] ?? 0,
                'suffix' => '支',
                'hint' => $avgArrowsPerDay ? '活躍日均 ' . $avgArrowsPerDay . ' 支' : '等待更多練習',
            ],
            [
                'label' => '當週訓練量',
                'value' => $arrowsWeek,
                'suffix' => '支',
                'hint' => $latestWeek['range'] ?? '—',
            ],
            [
                'label' => 'Streak',
                'value' => $stats['streak_days'] ?? 0,
                'suffix' => '天',
                'hint' => '保持連續訓練',
            ],
            [
                'label' => '最佳單趟',
                'value' => $stats['best_end'] ?? null,
                'suffix' => '分',
                'hint' => '6 箭合計',
            ],
            [
                'label' => '最佳 36 箭',
                'value' => $stats['best_36'] ?? null,
                'suffix' => '分',
                'hint' => '完整一輪',
            ],
        ];
    }
    private function monthAgg(Carbon $from, Carbon $to): array
    {
        $userId = auth()->id();

        // 1) shots：跨所有場次的整體統計
        $shotAgg = ArcheryShot::query()
            ->whereHas('session', fn ($q) =>
            $q->where('user_id', $userId)
                ->whereBetween('created_at', [$from, $to])
            )
            ->selectRaw("
            COUNT(*) AS arrows,
            SUM(CASE WHEN is_x = 1 AND score = 10 THEN 1 ELSE 0 END) AS x_cnt,
            SUM(CASE WHEN score = 10 AND (is_x IS NULL OR is_x = 0) THEN 1 ELSE 0 END) AS ten_only,
            SUM(score) AS score_sum,
            STDDEV_SAMP(score) AS sigma
        ")
            ->first();

        // 2) sessions：活躍天數（以場次開始日期去重）
        //   若你要用 shots 的時間去重，把 DATE(created_at) 改為 shots 的欄位並 join
        $sessAgg = ArcherySession::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(DISTINCT DATE(created_at)) AS active_days')
            ->first();

        // 3) 取值與安全轉型
        $arrows   = (int) ($shotAgg->arrows     ?? 0);
        $scoreSum = (int) ($shotAgg->score_sum  ?? 0);
        $x        = (int) ($shotAgg->x_cnt      ?? 0);
        $tenOnly  = (int) ($shotAgg->ten_only   ?? 0);
         $sigma = is_null($shotAgg->sigma) ? 0.0 : (float) $shotAgg->sigma;

        // 4) 指標
        $avgPerArrow = $arrows > 0 ? $scoreSum / $arrows : 0.0;  // AAE
        $xRate       = $arrows > 0 ? $x / $arrows         : 0.0;  // X%
        $tenRate     = $arrows > 0 ? $tenOnly / $arrows   : 0.0;  // 10%（不含X）

        return [
            'arrows'      => $arrows,
            'active_days' => (int) ($sessAgg->active_days ?? 0),
            'aae'         => $avgPerArrow,
            'x_rate'      => $xRate,
            'ten_rate'    => $tenRate,
             'sigma'     => $sigma,
        ];
    }


    private function monthWindow(string $rel = 'current'): array
    {
        $curStart = now()->startOfMonth();
        $curEnd = now()->copy()->endOfMonth();          // 含當月今天之後也OK
        $prevStart = now()->subMonthNoOverflow()->startOfMonth();
        $prevEnd = now()->subMonthNoOverflow()->endOfMonth();

        return $rel === 'prev'
            ? [$prevStart, $prevEnd]
            : [$curStart, $curEnd];
    }


}

