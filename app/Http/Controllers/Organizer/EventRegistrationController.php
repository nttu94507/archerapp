<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventRegistrationController extends Controller
{
    public function index(Request $request, Event $event): View
    {
        $this->authorize('manageRegistrations', $event);
        $query = $event->registrations()->with(['user', 'event_group']);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('q')) $query->where(fn ($q) => $q->where('name', 'like', '%'.$request->q.'%')->orWhere('email', 'like', '%'.$request->q.'%'));
        $registrations = $query->orderBy('event_group_id')->orderBy('name')->paginate(30)->withQueryString();

        return view('organizer.registrations.index', compact('event', 'registrations'));
    }

    public function update(Request $request, Event $event, EventRegistration $registration): RedirectResponse
    {
        $this->authorize('manageRegistrations', $event);
        $this->ensureBelongs($event, $registration);
        $validated = $request->validate([
            'status' => ['required', 'in:registered,checked_in,withdrawn,refunded,no_show'],
            'paid' => ['nullable', 'boolean'],
        ]);
        $this->applyStatus($registration, $validated['status'], $request->user()->id);
        if (array_key_exists('paid', $validated)) $registration->update(['paid' => $request->boolean('paid')]);
        $this->audit($event, $request, 'registration.updated', $registration, $validated);
        return back()->with('success', '報名狀態已更新。');
    }

    public function bulk(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageRegistrations', $event);
        $validated = $request->validate(['registration_ids' => ['required', 'array', 'min:1'], 'registration_ids.*' => ['integer'], 'status' => ['required', 'in:registered,checked_in,withdrawn,refunded,no_show']]);
        $registrations = $event->registrations()->whereIn('id', $validated['registration_ids'])->get();
        abort_if($registrations->count() !== count(array_unique($validated['registration_ids'])), 422, '包含無效報名。');
        foreach ($registrations as $registration) $this->applyStatus($registration, $validated['status'], $request->user()->id);
        $this->audit($event, $request, 'registration.bulk_updated', null, ['count' => $registrations->count(), 'status' => $validated['status']]);
        return back()->with('success', '已更新 '.$registrations->count().' 筆報名。');
    }

    public function checkIn(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageRegistrations', $event);
        $validated = $request->validate(['uuid' => ['required', 'uuid']]);
        $user = User::where('uuid', $validated['uuid'])->first();
        $registration = $user ? $event->registrations()->where('user_id', $user->id)->where('status', 'registered')->first() : null;
        if (! $registration) return back()->with('error', '找不到此會員可報到的有效報名。');
        $this->applyStatus($registration, 'checked_in', $request->user()->id);
        $this->audit($event, $request, 'registration.checked_in', $registration);
        return back()->with('success', $registration->name.' 已完成報到。');
    }

    private function applyStatus(EventRegistration $registration, string $status, int $actorId): void
    {
        $registration->update([
            'status' => $status,
            'checked_in_at' => $status === 'checked_in' ? ($registration->checked_in_at ?? now()) : null,
            'checked_in_by' => $status === 'checked_in' ? $actorId : null,
        ]);
    }

    private function ensureBelongs(Event $event, EventRegistration $registration): void { abort_unless($registration->event_id === $event->id, 404); }
    private function audit(Event $event, Request $request, string $action, ?EventRegistration $registration, array $metadata = []): void
    {
        EventAuditLog::create(['event_id'=>$event->id,'user_id'=>$request->user()->id,'action'=>$action,'subject_type'=>$registration ? EventRegistration::class : null,'subject_id'=>$registration?->id,'metadata'=>$metadata]);
    }
}
