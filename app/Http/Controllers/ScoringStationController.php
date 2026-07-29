<?php

namespace App\Http\Controllers;

use App\Models\EventAuditLog;
use App\Models\EventScoreEntry;
use App\Models\EventScoringTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScoringStationController extends Controller
{
    public function show(Request $request, string $token)
    {
        $target = $this->target($token);

        if ($this->isAuthorizedDevice($request, $target)) {
            $this->loadScoringData($target);
            $endNumber = min($target->last_completed_end + 1, $target->session->totalEnds());

            return view('scoring-stations.show', compact('target', 'endNumber'));
        }

        if ($target->device_token_hash !== null) {
            return response()->view('scoring-stations.device-locked', [], 423);
        }

        return view('scoring-stations.claim', compact('target'));
    }

    public function claim(Request $request, string $token): RedirectResponse
    {
        $target = $this->target($token);
        $validated = $request->validate([
            'pin'=>['required', 'digits:6'],
        ]);
        $deviceToken = null;

        $result = DB::transaction(function () use ($target, $validated, $request, &$deviceToken): string {
            $lockedTarget = EventScoringTarget::whereKey($target->id)->lockForUpdate()->firstOrFail();

            if ($lockedTarget->device_token_hash !== null) {
                return 'locked';
            }

            if (! hash_equals((string) $lockedTarget->device_pin, (string) $validated['pin'])) {
                return 'invalid_pin';
            }

            $deviceToken = Str::random(64);
            $lockedTarget->update([
                'device_token_hash'=>hash('sha256', $deviceToken),
                'device_bound_at'=>now(),
                'device_last_seen_at'=>now(),
                'device_user_agent'=>Str::limit((string) $request->userAgent(), 500, ''),
            ]);

            EventAuditLog::create([
                'event_id'=>$target->session->event_id,
                'action'=>'scoring.target_device_bound',
                'subject_type'=>EventScoringTarget::class,
                'subject_id'=>$target->id,
                'metadata'=>['target'=>$target->target_number],
            ]);

            return 'claimed';
        });

        if ($result === 'locked') {
            return redirect()->route('scoring-stations.show', $token);
        }
        if ($result === 'invalid_pin') {
            return back()->withInput()->withErrors(['pin'=>'PIN 碼錯誤，請向主辦方確認後再輸入。']);
        }

        Cookie::queue(cookie(
            $this->deviceCookieName($target),
            $deviceToken,
            60 * 24 * 30,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'strict'
        ));

        return redirect()->route('scoring-stations.show', $token);
    }

    public function storeEnd(Request $request, string $token): RedirectResponse
    {
        $target = $this->target($token);
        if (! $this->isAuthorizedDevice($request, $target)) {
            return redirect()->route('scoring-stations.show', $token);
        }
        $this->loadScoringData($target);

        $session = $target->session;
        if ($target->status === 'completed') {
            return back()->with('error', '此靶位已完成全部計分。');
        }

        $expectedEnd = $target->last_completed_end + 1;
        $validated = $request->validate([
            'end_number'=>['required', 'integer', 'in:'.$expectedEnd],
            'scores'=>['nullable', 'array'],
        ]);

        $assignments = $target->assignments;
        $normalizedScores = [];
        foreach ($assignments as $assignment) {
            $scores = $validated['scores'][$assignment->event_registration_id] ?? [];
            $scores = is_array($scores) ? array_values($scores) : [];
            $scores = array_pad(array_slice($scores, 0, $session->arrows_per_end), $session->arrows_per_end, 'M');
            $scores = array_map(fn ($score) => trim((string) $score) === '' ? 'M' : $score, $scores);
            foreach ($scores as $score) {
                if (! preg_match('/^(X|10|[1-9]|M)$/i', (string) $score)) {
                    throw ValidationException::withMessages(['scores'=>'箭值只能是 X、10～1 或 M。']);
                }
            }
            $normalizedScores[$assignment->event_registration_id] = $scores;
        }

        DB::transaction(function () use ($target, $session, $assignments, $normalizedScores, $expectedEnd): void {
            foreach ($assignments as $assignment) {
                $registration = $assignment->registration;
                $scores = collect($normalizedScores[$registration->id])
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
            ->with([
                'session.event',
                'session.group',
            ])
            ->firstOrFail();
    }

    private function isAuthorizedDevice(Request $request, EventScoringTarget $target): bool
    {
        $deviceToken = $request->cookie($this->deviceCookieName($target));
        if (! is_string($deviceToken) || $target->device_token_hash === null
            || ! hash_equals($target->device_token_hash, hash('sha256', $deviceToken))) {
            return false;
        }

        return DB::transaction(function () use ($target, $deviceToken): bool {
            $lockedTarget = EventScoringTarget::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($lockedTarget->device_token_hash === null
                || ! hash_equals($lockedTarget->device_token_hash, hash('sha256', $deviceToken))) {
                return false;
            }

            $lockedTarget->update(['device_last_seen_at'=>now()]);

            return true;
        });
    }

    private function loadScoringData(EventScoringTarget $target): void
    {
        $target->load([
            'assignments.registration.scoreEntries' => fn ($query) => $query->orderBy('end_number'),
        ]);
    }

    private function deviceCookieName(EventScoringTarget $target): string
    {
        return 'scoring_device_'.$target->id;
    }
}
