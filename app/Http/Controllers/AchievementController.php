<?php

namespace App\Http\Controllers;

use App\Services\AchievementProgressService;
use App\Models\AchievementDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AchievementController extends Controller
{
    public function __construct(private readonly AchievementProgressService $achievementProgressService)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $this->achievementProgressService->syncForUser($user);

        $progressRecords = $user->achievementProgress()
            ->with('definition')
            ->orderByDesc('unlocked_at')
            ->orderByDesc('progress_percent')
            ->get();

        $unlocked = $progressRecords->whereNotNull('unlocked_at');
        $inProgress = $this->visibleInProgressAchievements($progressRecords);
        $badgeStyles = $this->badgeStylesByDefinitionId();

        return view('achievements.index', [
            'unlocked' => $unlocked,
            'inProgress' => $inProgress,
            'badgeStyles' => $badgeStyles,
        ]);
    }

    /**
     * 依照同系列目標難度，回傳銅/銀/金等級徽章樣式。
     *
     * @return Collection<int, array<string, string>>
     */
    private function badgeStylesByDefinitionId(): Collection
    {
        return AchievementDefinition::query()
            ->orderBy('condition_type')
            ->orderBy('target_value')
            ->get()
            ->groupBy('condition_type')
            ->flatMap(function (Collection $items) {
                return $items->values()->mapWithKeys(function ($item, $index) {
                    $style = match (true) {
                        $index >= 2 => [
                            'icon' => '🏆',
                            'label' => '金牌',
                            'bg' => 'bg-amber-100',
                            'text' => 'text-amber-700',
                            'ring' => 'ring-amber-200',
                            'progress' => 'bg-gradient-to-r from-amber-400 to-yellow-500',
                        ],
                        $index === 1 => [
                            'icon' => '🥈',
                            'label' => '銀牌',
                            'bg' => 'bg-slate-100',
                            'text' => 'text-slate-700',
                            'ring' => 'ring-slate-300',
                            'progress' => 'bg-gradient-to-r from-slate-400 to-slate-500',
                        ],
                        default => [
                            'icon' => '🥉',
                            'label' => '銅牌',
                            'bg' => 'bg-orange-100',
                            'text' => 'text-orange-700',
                            'ring' => 'ring-orange-200',
                            'progress' => 'bg-gradient-to-r from-orange-400 to-amber-500',
                        ],
                    };

                    return [$item->id => $style];
                });
            });
    }

    /**
     * 只顯示每個系列「下一個」尚未解鎖的目標。
     * 例如箭數系列會先顯示 100，解鎖後才顯示 1000，再來 5000。
     *
     * @param Collection<int, mixed> $progressRecords
     * @return Collection<int, mixed>
     */
    private function visibleInProgressAchievements(Collection $progressRecords): Collection
    {
        return $progressRecords
            ->whereNull('unlocked_at')
            ->groupBy(fn ($item) => $item->definition->condition_type)
            ->map(function (Collection $items) {
                return $items
                    ->sortBy(fn ($item) => $item->definition->target_value)
                    ->first();
            })
            ->filter()
            ->values();
    }
}
