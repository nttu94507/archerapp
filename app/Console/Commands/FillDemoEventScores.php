<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventScoreEntry;
use App\Models\EventScoringSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FillDemoEventScores extends Command
{
    protected $signature = 'demo:fill-scores
        {event : 已排靶賽事的 UUID 或資料庫 ID}
        {--overwrite : 覆寫既有每趟成績；未指定時只補缺少的趟數}';

    protected $description = '填滿已排靶賽事所有靶位的排名賽分數，並完成靶位、場次及排名階段';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('此指令只允許在 local 或 testing 環境執行。');

            return self::FAILURE;
        }

        $reference = (string) $this->argument('event');
        $event = Event::query()
            ->where('uuid', $reference)
            ->when(ctype_digit($reference), fn ($query) => $query->orWhereKey((int) $reference))
            ->first();
        if (! $event) {
            $this->error('找不到賽事：'.$reference);

            return self::FAILURE;
        }
        if ($event->isOfficiallyCompleted()) {
            $this->error('賽事已正式完成，不能再填入測試分數。');

            return self::FAILURE;
        }

        $sessions = $event->scoringSessions()
            ->with(['phase', 'targets.assignments.registration'])
            ->orderBy('id')->get();
        if ($sessions->isEmpty()) {
            $this->error('此賽事尚未排靶，請先在靶位與計分執行排靶。');

            return self::FAILURE;
        }

        $overwrite = (bool) $this->option('overwrite');
        $entriesWritten = 0;
        $athletesCompleted = 0;
        $targetsCompleted = 0;

        DB::transaction(function () use (
            $event, $sessions, $overwrite, &$entriesWritten, &$athletesCompleted, &$targetsCompleted
        ): void {
            foreach ($sessions as $session) {
                $eligibleAssignments = $session->targets->flatMap->assignments
                    ->filter(fn ($assignment) => $assignment->registration
                        && in_array($assignment->registration->status, ['registered', 'checked_in'], true)
                        && $assignment->registration->result_status !== 'dns')
                    ->unique('event_registration_id')
                    ->sortBy('event_registration_id')->values();
                $rankIndex = $eligibleAssignments->pluck('event_registration_id')->flip();
                $totalEnds = $session->totalEnds();
                $arrowsPerEnd = max(1, (int) $session->arrows_per_end);

                foreach ($session->targets as $target) {
                    $targetAssignments = $target->assignments->filter(fn ($assignment) => $rankIndex->has($assignment->event_registration_id));
                    if ($targetAssignments->isEmpty()) {
                        $target->update(['status'=>'dns', 'last_synced_at'=>now()]);
                        continue;
                    }

                    foreach ($targetAssignments as $assignment) {
                        $registration = $assignment->registration;
                        $deduction = (int) $rankIndex->get($registration->id);
                        for ($end = 1; $end <= $totalEnds; $end++) {
                            $scores = collect(range(0, $arrowsPerEnd - 1))->map(function (int $arrow) use ($deduction, $end, $arrowsPerEnd): string {
                                $absoluteArrow = (($end - 1) * $arrowsPerEnd) + $arrow;
                                $arrowDeduction = max(0, min(10, $deduction - ($absoluteArrow * 10)));
                                if ($arrowDeduction === 0) return $absoluteArrow % 6 === 0 ? 'X' : '10';
                                if ($arrowDeduction === 10) return 'M';

                                return (string) (10 - $arrowDeduction);
                            })->sortByDesc(fn (string $score) => $score === 'X' ? 11 : ($score === 'M' ? 0 : (int) $score))->values()->all();
                            $total = collect($scores)->sum(fn (string $score) => $score === 'X' ? 10 : ($score === 'M' ? 0 : (int) $score));
                            $attributes = ['event_registration_id'=>$registration->id, 'end_number'=>$end];
                            $values = ['event_id'=>$event->id, 'user_id'=>$registration->user_id, 'scores'=>$scores, 'end_total'=>$total];
                            if ($overwrite) {
                                EventScoreEntry::updateOrCreate($attributes, $values);
                                $entriesWritten++;
                            } elseif (! EventScoreEntry::where($attributes)->exists()) {
                                EventScoreEntry::create(array_merge($attributes, $values));
                                $entriesWritten++;
                            }
                        }
                        $registration->update([
                            'score_submitted_at'=>now(),
                            'result_status'=>$registration->result_status ?: 'completed',
                        ]);
                        $athletesCompleted++;
                    }

                    $target->update([
                        'last_completed_end'=>$totalEnds, 'last_synced_at'=>now(), 'status'=>'completed',
                        'first_round_completed_at'=>$session->total_arrows === 72 ? now() : $target->first_round_completed_at,
                        'second_round_started_at'=>$session->total_arrows === 72 ? now() : $target->second_round_started_at,
                    ]);
                    $targetsCompleted++;
                }

                $session->update([
                    'status'=>'completed', 'started_at'=>$session->started_at ?: now(), 'completed_at'=>now(),
                ]);
                if ($session->phase && ! in_array($session->phase->status, ['published'], true)) {
                    $session->phase->update([
                        'status'=>'completed', 'started_at'=>$session->phase->started_at ?: now(), 'completed_at'=>now(),
                    ]);
                }
            }

            EventAuditLog::create([
                'event_id'=>$event->id, 'action'=>'demo.scores_filled',
                'metadata'=>[
                    'overwrite'=>$overwrite, 'entries'=>$entriesWritten,
                    'athletes'=>$athletesCompleted, 'targets'=>$targetsCompleted,
                ],
            ]);
        });

        $this->info('Demo 靶位分數已填滿。');
        $this->table(['項目', '數量'], [
            ['計分場次', $sessions->count()],
            ['完成靶位', $targetsCompleted],
            ['完成選手', $athletesCompleted],
            [$overwrite ? '寫入／覆寫趟數' : '補入缺少趟數', $entriesWritten],
        ]);
        $this->line('下一步：進入裁判工作台核對，再到成績管理確認與發布。');

        return self::SUCCESS;
    }
}
