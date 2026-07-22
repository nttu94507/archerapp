<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventStaff;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $events = Event::query()
            ->whereHas('staff', fn ($query) => $query->where('user_id', $request->user()->id)->where('status', 'active'))
            ->withCount([
                'groups', 'registrations', 'badges',
                'registrations as checked_in_count' => fn ($query) => $query->where('status', 'checked_in'),
            ])
            ->orderByDesc('start_date')->paginate(15);

        $organizerProfile = $request->user()->organizerProfile;
        return view('organizer.events.index', compact('events','organizerProfile'));
    }

    public function create(): View
    {
        abort_unless(request()->user()->canCreateEvents(),403);
        return view('organizer.events.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canCreateEvents(),403);
        $validated = $this->validateEvent($request);
        $validated['status'] = 'draft';
        $validated['verified'] = false;

        $event = DB::transaction(function () use ($validated, $request) {
            $event = Event::create($validated);
            $event->staff()->create([
                'user_id' => $request->user()->id, 'role' => 'owner', 'status' => 'active',
                'invited_by' => $request->user()->id, 'invited_at' => now(), 'accepted_at' => now(),
            ]);
            $this->audit($event, $request, 'event.created');
            return $event;
        });

        return redirect()->route('organizer.events.show', $event)->with('success', '賽事草稿已建立。');
    }

    public function show(Request $request, Event $event): View
    {
        $this->authorize('viewManagement', $event);
        $event->load(['groups' => fn ($query) => $query->withCount('registrations'), 'staff.user'])
            ->loadCount(['registrations', 'badges']);
        $statusCounts = $event->registrations()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $auditLogs = $event->auditLogs()->with('user')->limit(20)->get();
        $staffInviteQrs = [];
        if ($request->user()->can('manageStaff', $event)) {
            $writer = new Writer(new ImageRenderer(new RendererStyle(280, 2), new SvgImageBackEnd));
            foreach (['manager', 'staff', 'viewer'] as $role) {
                $url = URL::temporarySignedRoute('organizer.staff-invitations.show', now()->addDay(), [
                    'event' => $event, 'role' => $role, 'inviter' => $request->user()->id,
                ]);
                $staffInviteQrs[$role] = 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($url));
            }
        }

        return view('organizer.events.show', compact('event', 'statusCounts', 'auditLogs', 'staffInviteQrs'));
    }

    public function edit(Event $event): View
    {
        $this->authorize('update', $event);
        return view('organizer.events.edit', compact('event'));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $event->update($this->validateEvent($request));
        $this->audit($event, $request, 'event.updated');

        return redirect()->route('organizer.events.show', $event)->with('success', '賽事資料已更新。');
    }

    public function submit(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless(in_array($event->status, ['draft', 'rejected'], true), 422);
        abort_if($event->groups()->doesntExist(), 422, '至少建立一個組別才能送審。');
        $event->update(['status' => 'pending', 'review_note' => null, 'published_at' => null, 'verified' => false]);
        $this->audit($event, $request, 'event.submitted');

        return back()->with('success', '賽事已送交平台審核。');
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $event->update(['cancelled_at' => now()]);
        $this->audit($event, $request, 'event.cancelled');

        return back()->with('success', '賽事已取消並停止公開報名。');
    }

    public function addStaff(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageStaff', $event);
        $validated = $request->validate(['email' => ['required', 'email', 'exists:users,email'], 'role' => ['required', 'in:manager,staff,viewer']]);
        $user = User::where('email', $validated['email'])->firstOrFail();
        EventStaff::updateOrCreate(['event_id' => $event->id, 'user_id' => $user->id], [
            'role' => $validated['role'], 'status' => 'active', 'invited_by' => $request->user()->id,
            'invited_at' => now(), 'accepted_at' => now(),
        ]);
        $this->audit($event, $request, 'staff.added', $user->id, ['role' => $validated['role']]);

        return back()->with('success', '工作人員已加入。');
    }

    public function revokeStaff(Request $request, Event $event, EventStaff $staff): RedirectResponse
    {
        $this->authorize('manageStaff', $event);
        abort_unless($staff->event_id === $event->id, 404);
        abort_if($staff->role === 'owner', 422, '不能移除賽事擁有者。');
        $staff->update(['status' => 'revoked']);
        $this->audit($event, $request, 'staff.revoked', $staff->user_id);

        return back()->with('success', '工作人員權限已撤銷。');
    }

    public function showStaffInvitation(Request $request, Event $event, string $role): View
    {
        abort_unless(in_array($role, ['manager', 'staff', 'viewer'], true), 404);
        return view('organizer.events.staff-invitation', compact('event', 'role'));
    }

    public function acceptStaffInvitation(Request $request, Event $event, string $role): RedirectResponse
    {
        abort_unless(in_array($role, ['manager', 'staff', 'viewer'], true), 404);
        $inviter = User::findOrFail($request->integer('inviter'));
        abort_unless($inviter->can('manageStaff', $event), 403, '這份邀請已失效。');
        abort_if($event->staff()->where('user_id', $request->user()->id)->where('role', 'owner')->exists(), 422, '賽事擁有者不需要加入邀請。');

        EventStaff::updateOrCreate(['event_id' => $event->id, 'user_id' => $request->user()->id], [
            'role' => $role, 'status' => 'active', 'invited_by' => $inviter->id,
            'invited_at' => now(), 'accepted_at' => now(),
        ]);
        $this->audit($event, $request, 'staff.invitation_accepted', $request->user()->id, ['role' => $role]);

        return redirect()->route('organizer.events.show', $event)->with('success', '已加入 '.$event->name.' 的工作團隊。');
    }

    private function validateEvent(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'], 'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'], 'mode' => ['required', 'in:indoor,outdoor'],
            'level' => ['nullable', 'string', 'max:50'], 'organizer' => ['required', 'string', 'max:120'],
            'reg_start' => ['nullable', 'date'], 'reg_end' => ['nullable', 'date', 'after_or_equal:reg_start'],
            'venue' => ['nullable', 'string', 'max:255'], 'map_link' => ['nullable', 'url'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'], 'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
    }

    private function audit(Event $event, Request $request, string $action, ?int $subjectId = null, array $metadata = []): void
    {
        EventAuditLog::create(['event_id' => $event->id, 'user_id' => $request->user()->id, 'action' => $action, 'subject_type' => $subjectId ? User::class : Event::class, 'subject_id' => $subjectId ?? $event->id, 'metadata' => $metadata]);
    }
}
