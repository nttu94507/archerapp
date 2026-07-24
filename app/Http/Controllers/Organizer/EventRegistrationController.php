<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventRegistration;
use App\Models\EventPaymentAudit;
use App\Models\User;
use App\Services\EventBadgeAwardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventRegistrationController extends Controller
{
    public function index(Request $request, Event $event): View
    {
        $this->authorize('manageRegistrations', $event);
        $activeStatuses = ['registered', 'checked_in'];
        $groups = $event->groups()
            ->withCount([
                'registrations as active_registrations_count' => fn ($query) => $query->whereIn('status', $activeStatuses),
                'registrations as paid_registrations_count' => fn ($query) => $query->whereIn('status', $activeStatuses)->where('payment_status', 'paid'),
                'registrations as exempt_registrations_count' => fn ($query) => $query->whereIn('status', $activeStatuses)->where('payment_status', 'exempt'),
                'registrations as pending_payment_count' => fn ($query) => $query->whereIn('status', $activeStatuses)->where('payment_status', 'pending'),
                'registrations as payment_issue_count' => fn ($query) => $query->whereIn('status', $activeStatuses)->where('payment_status', 'issue'),
            ])
            ->orderBy('name')
            ->get();

        $selectedGroup = $request->filled('event_group_id')
            ? $groups->firstWhere('id', $request->integer('event_group_id'))
            : null;

        $registrations = null;
        if ($selectedGroup) {
            $query = $event->registrations()
                ->with(['user', 'event_group'])
                ->where('event_group_id', $selectedGroup->id);
            if ($request->filled('status')) $query->where('status', $request->status);
            if ($request->filled('payment_status')) $query->where('payment_status', $request->payment_status);
            if ($request->filled('q')) {
                $keyword = trim((string) $request->q);
                $query->where(fn ($q) => $q
                    ->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('email', 'like', '%'.$keyword.'%')
                    ->orWhere('team_name', 'like', '%'.$keyword.'%')
                    ->orWhereHas('user', fn ($user) => $user
                        ->where('uuid', 'like', '%'.$keyword.'%')
                        ->orWhere('nickname', 'like', '%'.$keyword.'%')));
            }
            $registrations = $query->orderByRaw("CASE WHEN status IN ('registered','checked_in') THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString();
        }

        $totals = [
            'groups' => $groups->count(),
            'registrations' => $groups->sum('active_registrations_count'),
            'paid' => $groups->sum(fn ($group) => (int) $group->fee === 0
                ? (int) $group->active_registrations_count
                : (int) $group->paid_registrations_count + (int) $group->exempt_registrations_count),
            'pending' => $groups->sum(fn ($group) => (int) $group->fee === 0 ? 0 : (int) $group->pending_payment_count),
            'issues' => $groups->sum('payment_issue_count'),
        ];

        return view('organizer.registrations.index', compact('event', 'registrations', 'groups', 'selectedGroup', 'totals'));
    }

    public function update(Request $request, Event $event, EventRegistration $registration, EventBadgeAwardService $badges): RedirectResponse
    {
        $this->authorize('manageRegistrations', $event);
        $this->ensureBelongs($event, $registration);
        $validated = $request->validate([
            'status' => ['required', 'in:registered,checked_in,withdrawn,refunded,no_show'],
            'paid' => ['nullable', 'boolean'],
        ]);
        $this->applyStatus($registration, $validated['status'], $request->user()->id);
        if (array_key_exists('paid', $validated)) {
            $this->setPayment($registration, $request->boolean('paid') ? 'paid' : 'pending', $request->user()->id);
        }
        $badges->awardAttendanceFor($registration->fresh());
        $this->audit($event, $request, 'registration.updated', $registration, $validated);
        return back()->with('success', '報名狀態已更新。');
    }

    public function bulk(Request $request, Event $event, EventBadgeAwardService $badges): RedirectResponse
    {
        $this->authorize('manageRegistrations', $event);
        $validated = $request->validate(['registration_ids' => ['required', 'array', 'min:1'], 'registration_ids.*' => ['integer'], 'status' => ['required', 'in:registered,checked_in,withdrawn,refunded,no_show']]);
        $registrations = $event->registrations()->whereIn('id', $validated['registration_ids'])->get();
        abort_if($registrations->count() !== count(array_unique($validated['registration_ids'])), 422, '包含無效報名。');
        foreach ($registrations as $registration) {
            $this->applyStatus($registration, $validated['status'], $request->user()->id);
            $badges->awardAttendanceFor($registration->fresh());
        }
        $this->audit($event, $request, 'registration.bulk_updated', null, ['count' => $registrations->count(), 'status' => $validated['status']]);
        return back()->with('success', '已更新 '.$registrations->count().' 筆報名。');
    }

    public function checkIn(Request $request, Event $event, EventBadgeAwardService $badges): RedirectResponse
    {
        $this->authorize('manageRegistrations', $event);
        $validated = $request->validate(['uuid' => ['required', 'uuid']]);
        $user = User::where('uuid', $validated['uuid'])->first();
        $registration = $user ? $event->registrations()->where('user_id', $user->id)->where('status', 'registered')->first() : null;
        if (! $registration) return back()->with('error', '找不到此會員可報到的有效報名。');
        $this->applyStatus($registration, 'checked_in', $request->user()->id);
        $badges->awardAttendanceFor($registration->fresh());
        $this->audit($event, $request, 'registration.checked_in', $registration);
        return back()->with('success', $registration->name.' 已完成報到。');
    }

    public function bulkPayment(Request $request, Event $event, EventBadgeAwardService $badges): RedirectResponse
    {
        $this->authorize('manageRegistrations', $event);
        $validated = $request->validate([
            'registration_ids'=>['required','array','min:1'], 'registration_ids.*'=>['integer'],
            'payment_status'=>['required','in:pending,paid,exempt,refunded,issue'],
            'payment_amount'=>['nullable','numeric','min:0'], 'payment_method'=>['nullable','string','max:30'],
            'payment_reference'=>['nullable','string','max:120'], 'payment_note'=>['nullable','string','max:1000'],
        ]);
        $registrations = $event->registrations()->whereIn('id', $validated['registration_ids'])->get();
        abort_if($registrations->count() !== count(array_unique($validated['registration_ids'])), 422, '包含無效報名。');
        foreach ($registrations as $registration) {
            $this->setPayment($registration, $validated['payment_status'], $request->user()->id, $validated);
            $badges->awardAttendanceFor($registration->fresh());
        }
        $this->audit($event, $request, 'registration.payment_bulk_updated', null, ['count'=>$registrations->count(),'payment_status'=>$validated['payment_status']]);
        return back()->with('success', '已更新 '.$registrations->count().' 筆繳費狀態。');
    }

    private function applyStatus(EventRegistration $registration, string $status, int $actorId): void
    {
        $registration->update([
            'status' => $status,
            'checked_in_at' => $status === 'checked_in' ? ($registration->checked_in_at ?? now()) : null,
            'checked_in_by' => $status === 'checked_in' ? $actorId : null,
        ]);
    }

    private function setPayment(EventRegistration $registration, string $status, int $actorId, array $data = []): void
    {
        $from = $registration->payment_status ?? ($registration->paid ? 'paid' : 'pending');
        $settled = in_array($status, ['paid', 'exempt'], true);
        $registration->update([
            'paid'=>$settled, 'payment_status'=>$status,
            'payment_confirmed_at'=>$settled ? now() : null, 'payment_confirmed_by'=>$settled ? $actorId : null,
            'payment_amount'=>$data['payment_amount'] ?? $registration->payment_amount,
            'payment_method'=>$data['payment_method'] ?? $registration->payment_method,
            'payment_reference'=>$data['payment_reference'] ?? $registration->payment_reference,
            'payment_note'=>$data['payment_note'] ?? $registration->payment_note,
        ]);
        EventPaymentAudit::create([
            'event_registration_id'=>$registration->id, 'changed_by'=>$actorId, 'from_status'=>$from, 'to_status'=>$status,
            'amount'=>$data['payment_amount'] ?? null, 'payment_method'=>$data['payment_method'] ?? null,
            'payment_reference'=>$data['payment_reference'] ?? null, 'note'=>$data['payment_note'] ?? null,
        ]);
    }

    private function ensureBelongs(Event $event, EventRegistration $registration): void { abort_unless($registration->event_id === $event->id, 404); }
    private function audit(Event $event, Request $request, string $action, ?EventRegistration $registration, array $metadata = []): void
    {
        EventAuditLog::create(['event_id'=>$event->id,'user_id'=>$request->user()->id,'action'=>$action,'subject_type'=>$registration ? EventRegistration::class : null,'subject_id'=>$registration?->id,'metadata'=>$metadata]);
    }
}
