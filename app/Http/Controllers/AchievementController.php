<?php

namespace App\Http\Controllers;

use App\Services\AchievementProgressService;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function __construct(private readonly AchievementProgressService $achievementProgressService)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $this->achievementProgressService->syncForUser($user);

        $records = $user->achievementProgress()
            ->with('definition')
            ->whereHas('definition', fn ($q) => $q->where('is_active', true))
            ->get()
            ->sortBy(fn ($item) => [
                $this->groupOrder($item->definition->category ?? ''),
                (int) ($item->definition->target_value ?? 0),
            ])
            ->values();

        $groups = $records->groupBy(fn ($item) => $item->definition->category ?? 'others');

        $availableTitles = $records
            ->whereNotNull('unlocked_at')
            ->pluck('definition.title_name')
            ->filter()
            ->unique()
            ->values();

        return view('achievements.index', [
            'groups' => $groups,
            'groupTitles' => $this->groupTitles(),
            'availableTitles' => $availableTitles,
            'summary' => [
                'total' => $records->count(),
                'unlocked' => $records->whereNotNull('unlocked_at')->count(),
            ],
        ]);
    }

    /** @return array<string,string> */
    private function groupTitles(): array
    {
        return [
            'core_accumulate' => '🥇 核心成就・累積型',
            'core_growth' => '📈 核心成就・成長型',
            'core_stability' => '🔥 核心成就・穩定型',
            'core_habit' => '🧠 核心成就・習慣型',
            'distance_18' => '🏹 18m 距離成就',
            'distance_30' => '🏹 30m 距離成就',
            'distance_50' => '🏹 50m 距離成就',
            'distance_70' => '🏹 70m 距離成就',
            'cross_distance' => '🚀 跨距離成就',
            'level' => '🎖️ 等級成就',
        ];
    }

    private function groupOrder(string $group): int
    {
        $order = array_keys($this->groupTitles());
        $idx = array_search($group, $order, true);

        return $idx === false ? 999 : $idx;
    }
}
