<?php

namespace App\Http\Controllers;

use App\Models\EventAuditLog;
use App\Models\EventScoreEntry;
use App\Models\EventScoringTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScoringStationController extends Controller
{
    public function show(string $token): View
    {
        $target = $this->target($token);
        $endNumber = min($target->last_completed_end + 1, $target->session->totalEnds());

        return view('scoring-stations.show', compact('target', 'endNumber'));
    }

    public function storeEnd(Request $request, string $token): RedirectResponse
    {
        $target = $this->target($token);
        $session = $target->session;
        if ($target->status === 'completed') {
            return back()->with('error', '此靶位已完成全部計分。');
        }

        $expectedEnd = $target->last_completed_end + 1;
        $validated = $request->validate([
            'end_number'=>['required', 'integer', 'in:'.$expectedEnd],
            'scores'=>['required', 'array'],
        ]);

        $assignments = $target->assignments;
        foreach ($assignments as $assignment) {
            $scores = $validated['scores'][$assignment->event_registration_id] ?? null;
            if (! is_array($scores) || count($scores) !== $session->arrows_per_end) {
                throw ValidationException::withMessages(['scores'=>'每位選手都必須輸入 '.$session->arrows_per_end.' 支箭。']);
            }
            foreach ($scores as $score) {
                if (! preg_match('/^(X|10|[1-9]|M)$/i', (string) $score)) {
                    throw ValidationException::withMessages(['scores'=>'箭值只能是 X、10～1 或 M。']);
                }
            }
        }

        DB::transaction(function () use ($target, $session, $assignments, $validated, $expectedEnd): void {
            foreach ($assignments as $assignment) {
                $registration = $assignment->registration;
                $scores = collect($validated['scores'][$registration->id])
                    ->map(fn ($score) => strtoupper((string) $score))
                    ->sortByDesc(fn ($score) => $score === 'X' ? 11 : ($score === 'M' ? 0 : (int) $score))
                    ->values()
                    ->all();
                $total = collect($scores)->sum(fn ($score) => $score === 'X' ? 10 : ($score === 'M' ? 0 : (int) $score));
                EventScoreEntry::updateOrCreate(
                    ['event_registration_id'=>$registration->id, 'end_number'=>$expectedEnd],
                    ['event_id'=>$session->event_id, 'user_id'=>$registration->user_id, 'scores'=>$scores, 'end_total'=>$total]
                );
            }

            $completed = $expectedEnd >= $session->totalEnds();
            $target->update([
                'last_completed_end'=>$expectedEnd,
                'last_synced_at'=>now(),
                'status'=>$completed ? 'completed' : 'scoring',
            ]);
            if ($session->started_at === null) {
                $session->update(['status'=>'scoring', 'started_at'=>now()]);
            }
            if ($completed) {
                foreach ($assignments as $assignment) {
                    $assignment->registration->update(['score_submitted_at'=>now()]);
                }
                if ($session->targets()->where('status', '!=', 'completed')->doesntExist()) {
                    $session->update(['status'=>'completed', 'completed_at'=>now()]);
                }
            }

            EventAuditLog::create([
                'event_id'=>$session->event_id, 'action'=>'scoring.target_end_submitted',
                'subject_type'=>EventScoringTarget::class, 'subject_id'=>$target->id,
                'metadata'=>['target'=>$target->target_number, 'end'=>$expectedEnd],
            ]);
        });

        return redirect()->route('scoring-stations.show', $token)
            ->with('success', '第 '.$expectedEnd.' 趟已完成同步。');
    }

    private function target(string $token): EventScoringTarget
    {
        return EventScoringTarget::query()
            ->where('access_token', $token)
            ->with(['session.event', 'session.group', 'assignments.registration'])
            ->firstOrFail();
    }
}
