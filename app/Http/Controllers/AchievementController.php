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

        $progressRecords = $user->achievementProgress()
            ->with('definition')
            ->orderByDesc('unlocked_at')
            ->orderByDesc('progress_percent')
            ->get();

        $unlocked = $progressRecords->whereNotNull('unlocked_at');
        $inProgress = $progressRecords->whereNull('unlocked_at');

        return view('achievements.index', [
            'unlocked' => $unlocked,
            'inProgress' => $inProgress,
        ]);
    }
}
