<?php

namespace App\Http\Controllers;

use App\Services\AchievementProgressService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly AchievementProgressService $achievementProgressService)
    {
    }

    public function show(Request $request)
    {
        $user = $request->user()->load('profile');
        $this->achievementProgressService->syncForUser($user);

        $progress = $user->achievementProgress()
            ->with('definition')
            ->whereHas('definition', fn ($q) => $q->where('is_active', true))
            ->get();

        $unlocked = $progress->whereNotNull('unlocked_at');

        return view('profile.show', [
            'user' => $user,
            'profile' => $user->profile,
            'achievementSummary' => [
                'total' => $progress->count(),
                'unlocked' => $unlocked->count(),
                'in_progress' => $progress->whereNull('unlocked_at')->count(),
            ],
            'recentUnlocked' => $unlocked->sortByDesc('unlocked_at')->take(8)->values(),
        ]);
    }
}
