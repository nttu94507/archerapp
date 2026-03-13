<?php

namespace App\Http\Controllers;

use App\Services\AchievementProgressService;
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
        $availableTitles = $unlocked
            ->pluck('definition.title_name')
            ->filter()
            ->unique()
            ->values();

        return view('achievements.index', [
            'unlocked' => $unlocked,
            'inProgress' => $inProgress,
            'availableTitles' => $availableTitles,
        ]);
    }

    /**
     * 只顯示每個系列「下一個」尚未解鎖的目標。
     * 例如箭數系列會先顯示 5000，解鎖後才顯示 6000。
     *
     * @param Collection<int, mixed> $progressRecords
     * @return Collection<int, mixed>
     */
    private function visibleInProgressAchievements(Collection $progressRecords): Collection
    {
        return $progressRecords
            ->whereNull('unlocked_at')
            ->filter(fn ($item) => !($item->definition->is_hidden ?? false))
            ->groupBy(fn ($item) => $item->definition->category)
            ->map(function (Collection $items) {
                return $items
                    ->sortBy(fn ($item) => $item->definition->target_value)
                    ->first();
            })
            ->filter()
            ->values();
    }
}
