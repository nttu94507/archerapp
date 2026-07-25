<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use App\Models\User;
use App\Services\AchievementProgressService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('achievements:sync-active', function (AchievementProgressService $service) {
    $activeUserIds = DB::table('archery_sessions')
        ->where('updated_at', '>=', now()->subDays(7))
        ->distinct()
        ->pluck('user_id');

    $count = 0;
    User::query()->whereIn('id', $activeUserIds)->chunkById(200, function ($users) use ($service, &$count) {
        foreach ($users as $user) {
            $service->syncForUser($user);
            $count++;
        }
    });

    $this->info('Synced achievement progress for ' . $count . ' users.');
})->purpose('Sync achievement progress for users active in the last 7 days');

Schedule::command('achievements:sync-active')->dailyAt('03:15');
