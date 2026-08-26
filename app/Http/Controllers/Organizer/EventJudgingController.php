<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventScoringTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EventJudgingController extends Controller
{
    public function index(Request $request, Event $event): View
    {
        $this->authorize('manageJudging', $event);
        $event->load([
            'scoringSessions.group',
            'scoringSessions.targets.reviewer',
            'scoringSessions.targets.confirmer',
            'scoringSessions.targets.assignments.registration.scoreEntries' => fn ($query) => $query->orderBy('end_number'),
        ]);

        $role = $this->role($request, $event);
        $canConfirm = $request->user()->isAdmin() || in_array($role, ['owner', 'manager', 'chief_judge'], true);

        return view('organizer.judging.index', compact('event', 'role', 'canConfirm'));
    }

    public function update(Request $request, Event $event, EventScoringTarget $target): RedirectResponse
    {
        $this->authorize('manageJudging', $event);
        abort_unless($target->session()->where('event_id', $event->id)->exists(), 404);
        abort_if($target->status === 'dns', 422, '全靶選手皆為 DNS，無需裁判核對。');

        $role = $this->role($request, $event);
        $canConfirm = $request->user()->isAdmin() || in_array($role, ['owner', 'manager', 'chief_judge'], true);
        $allowed = $canConfirm ? ['reviewed', 'confirmed', 'disputed'] : ['reviewed', 'disputed'];
        $validated = $request->validate([
            'judge_status'=>['required', 'in:'.implode(',', $allowed)],
            'judge_note'=>[$request->string('judge_status')->toString() === 'disputed' ? 'required' : 'nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($event, $target, $validated, $request): void {
            $locked = EventScoringTarget::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $status = $validated['judge_status'];
            $updates = [
                'judge_status'=>$status,
                'judge_note'=>$validated['judge_note'] ?? null,
                'reviewed_by'=>$request->user()->id,
                'reviewed_at'=>now(),
            ];
            if ($status === 'confirmed') {
                $updates['confirmed_by'] = $request->user()->id;
                $updates['confirmed_at'] = now();
            } else {
                $updates['confirmed_by'] = null;
                $updates['confirmed_at'] = null;
            }
            $locked->update($updates);

            EventAuditLog::create([
                'event_id'=>$event->id,
                'user_id'=>$request->user()->id,
                'action'=>'judging.target_'.$status,
                'subject_type'=>EventScoringTarget::class,
                'subject_id'=>$target->id,
                'metadata'=>['target'=>$target->target_number, 'note'=>$validated['judge_note'] ?? null],
            ]);
        });

        return back()->with('success', '靶位裁判狀態已更新。');
    }

    private function role(Request $request, Event $event): ?string
    {
        return $event->staff()->where('user_id', $request->user()->id)->where('status', 'active')->value('role');
    }
}
