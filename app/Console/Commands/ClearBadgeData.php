<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClearBadgeData extends Command
{
    protected $signature = 'data:clear-badges
        {--badge=* : 只清除指定的 Badge ID，可重複使用}
        {--execute : 實際執行；未加時只預覽}
        {--yes : 不詢問確認，供自動化環境使用}';

    protected $description = '預覽或清除 Badge、申請、發放認證與上傳圖示';

    public function handle(): int
    {
        $requested = array_values(array_unique(array_filter(array_map('intval', $this->option('badge')))));
        $badges = DB::table('event_badges')
            ->when($requested, fn ($query) => $query->whereIn('id', $requested))
            ->orderBy('id')
            ->get(['id', 'name', 'icon_path']);

        if ($requested && $badges->count() !== count($requested)) {
            $this->error('包含不存在的 Badge ID，未執行任何操作。');

            return self::FAILURE;
        }

        if ($badges->isEmpty()) {
            $this->info('沒有可清除的 Badge 資料。');

            return self::SUCCESS;
        }

        $ids = $badges->pluck('id')->all();
        $counts = [
            'Badge' => $badges->count(),
            '領取申請' => DB::table('event_badge_claims')->whereIn('event_badge_id', $ids)->count(),
            '會員發放／公開認證' => DB::table('user_event_badges')->whereIn('event_badge_id', $ids)->count(),
            '發放活動' => DB::table('badge_campaigns')->whereIn('event_badge_id', $ids)->count(),
            '上傳圖示' => $badges->whereNotNull('icon_path')->count(),
        ];

        $this->table(
            ['資料', '筆數'],
            collect($counts)->map(fn ($count, $label) => [$label, $count])->values()->all()
        );

        if (! $this->option('execute')) {
            $this->warn('目前是預覽模式，沒有刪除任何資料。確認後請加上 --execute。');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm('確定永久清除以上 Badge 與會員取得紀錄？此操作無法復原。')) {
            $this->info('已取消。');

            return self::SUCCESS;
        }

        $icons = $badges->pluck('icon_path')->filter();

        DB::transaction(fn () => DB::table('event_badges')->whereIn('id', $ids)->delete());

        foreach ($icons as $path) {
            Storage::disk('public')->delete($path);
        }

        $this->info('已清除 '.$badges->count().' 個 Badge 及所有發放資料。');

        return self::SUCCESS;
    }
}
