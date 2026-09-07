<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ClearEventData extends Command
{
    protected $signature = 'data:clear-events
        {--event=* : 只清除指定的賽事 ID，可重複使用}
        {--execute : 實際執行；未加時只預覽}
        {--yes : 不詢問確認，供自動化環境使用}';

    protected $description = '預覽或清除賽事及其組別、報名、繳費、工作人員、成績、紀錄與賽事 Badge';

    public function handle(): int
    {
        $requested = array_values(array_unique(array_filter(array_map('intval', $this->option('event')))));
        $events = DB::table('events')
            ->when($requested, fn ($query) => $query->whereIn('id', $requested))
            ->orderBy('id')
            ->get(['id', 'name']);

        if ($requested && $events->count() !== count($requested)) {
            $this->error('包含不存在的賽事 ID，未執行任何操作。');

            return self::FAILURE;
        }

        if ($events->isEmpty()) {
            $this->info('沒有可清除的賽事資料。');

            return self::SUCCESS;
        }

        $ids = $events->pluck('id')->all();
        $badgeIds = DB::table('event_badges')->whereIn('event_id', $ids)->pluck('id');
        $registrationIds = DB::table('event_registrations')->whereIn('event_id', $ids)->pluck('id');
        $bracketIds = DB::table('event_elimination_brackets')->whereIn('event_id', $ids)->pluck('id');
        $matchIds = $bracketIds->isEmpty() ? collect() : DB::table('event_elimination_matches')->whereIn('event_elimination_bracket_id', $bracketIds)->pluck('id');
        $snapshotIds = DB::table('event_ranking_snapshots')->whereIn('event_id', $ids)->pluck('id');
        $sessionIds = DB::table('event_scoring_sessions')->whereIn('event_id', $ids)->pluck('id');
        $targetIds = $sessionIds->isEmpty() ? collect() : DB::table('event_scoring_targets')->whereIn('event_scoring_session_id', $sessionIds)->pluck('id');
        $teamIds = DB::table('event_teams')->whereIn('event_id', $ids)->pluck('id');
        $counts = [
            '賽事' => $events->count(),
            '組別' => $this->count('event_groups', 'event_id', $ids),
            '報名' => $this->count('event_registrations', 'event_id', $ids),
            '繳費異動' => $registrationIds->isEmpty()
                ? 0
                : DB::table('event_payment_audits')->whereIn('event_registration_id', $registrationIds)->count(),
            '工作團隊' => $this->count('event_staff', 'event_id', $ids),
            '成績明細' => $this->count('event_score_entries', 'event_id', $ids),
            '賽事總成績' => $this->count('scores', 'event_id', $ids),
            '計分場次與靶位' => $sessionIds->count() + $targetIds->count(),
            '排名快照' => $snapshotIds->count(),
            '對抗賽與場次' => $bracketIds->count() + $matchIds->count(),
            '賽事團隊' => $teamIds->count(),
            '操作紀錄' => $this->count('event_audit_logs', 'event_id', $ids),
            '賽事 Badge' => $badgeIds->count(),
            'Badge 發放紀錄' => $badgeIds->isEmpty()
                ? 0
                : DB::table('user_event_badges')->whereIn('event_badge_id', $badgeIds)->count(),
        ];

        $this->table(
            ['資料', '筆數'],
            collect($counts)->map(fn ($count, $label) => [$label, $count])->values()->all()
        );

        if (! $this->option('execute')) {
            $this->warn('目前是預覽模式，沒有刪除任何資料。確認後請加上 --execute。');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm('確定永久清除以上賽事資料？此操作無法復原。')) {
            $this->info('已取消。');

            return self::SUCCESS;
        }

        $icons = $badgeIds->isEmpty()
            ? collect()
            : DB::table('event_badges')->whereIn('id', $badgeIds)->whereNotNull('icon_path')->pluck('icon_path');

        Schema::withoutForeignKeyConstraints(function () use ($ids, $badgeIds, $registrationIds, $bracketIds, $matchIds, $snapshotIds, $sessionIds, $targetIds, $teamIds): void {
            DB::transaction(function () use ($ids, $badgeIds, $registrationIds, $bracketIds, $matchIds, $snapshotIds, $sessionIds, $targetIds, $teamIds): void {
                if ($badgeIds->isNotEmpty()) {
                    DB::table('user_event_badges')->whereIn('event_badge_id', $badgeIds)->delete();
                    DB::table('event_badge_claims')->whereIn('event_badge_id', $badgeIds)->delete();
                    DB::table('badge_campaigns')->whereIn('event_badge_id', $badgeIds)->delete();
                    DB::table('event_badges')->whereIn('id', $badgeIds)->delete();
                }

                if ($registrationIds->isNotEmpty()) {
                    DB::table('event_payment_audits')->whereIn('event_registration_id', $registrationIds)->delete();
                }

                if ($matchIds->isNotEmpty()) {
                    $this->deleteWhereIn('event_elimination_shoot_offs', 'event_elimination_match_id', $matchIds->all());
                    $this->deleteWhereIn('event_elimination_match_sets', 'event_elimination_match_id', $matchIds->all());
                    $this->deleteWhereIn('event_elimination_match_ends', 'event_elimination_match_id', $matchIds->all());
                    $this->deleteWhereIn('event_elimination_matches', 'id', $matchIds->all());
                }
                $this->deleteWhereIn('event_elimination_brackets', 'id', $bracketIds->all());
                $this->deleteWhereIn('event_ranking_snapshot_entries', 'event_ranking_snapshot_id', $snapshotIds->all());
                $this->deleteWhereIn('event_ranking_snapshots', 'id', $snapshotIds->all());
                $this->deleteWhereIn('event_scoring_assignments', 'event_scoring_target_id', $targetIds->all());
                $this->deleteWhereIn('event_scoring_targets', 'id', $targetIds->all());
                $this->deleteWhereIn('event_scoring_sessions', 'id', $sessionIds->all());
                $this->deleteWhereIn('event_team_members', 'event_team_id', $teamIds->all());
                $this->deleteWhereIn('event_teams', 'id', $teamIds->all());

                $this->deleteByEventId('event_score_entries', $ids);
                $this->deleteByEventId('scores', $ids);
                $this->deleteByEventId('event_audit_logs', $ids);
                $this->deleteByEventId('event_phases', $ids);
                $this->deleteByEventId('event_registrations', $ids);
                $this->deleteByEventId('event_staff', $ids);
                $this->deleteByEventId('event_groups', $ids);
                DB::table('events')->whereIn('id', $ids)->delete();
            });
        });

        foreach ($icons as $path) {
            Storage::disk('public')->delete($path);
        }

        $this->info('已清除 '.$events->count().' 場賽事及所有關聯資料。');

        return self::SUCCESS;
    }

    private function count(string $table, string $column, array $ids): int
    {
        return Schema::hasTable($table) ? DB::table($table)->whereIn($column, $ids)->count() : 0;
    }

    private function deleteByEventId(string $table, array $ids): void
    {
        if (Schema::hasTable($table)) {
            DB::table($table)->whereIn('event_id', $ids)->delete();
        }
    }

    private function deleteWhereIn(string $table, string $column, array $ids): void
    {
        if ($ids && Schema::hasTable($table)) {
            DB::table($table)->whereIn($column, $ids)->delete();
        }
    }
}
