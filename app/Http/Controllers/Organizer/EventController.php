<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventStaff;
use App\Models\User;
use App\Services\EventBadgeAwardService;
use App\Services\EventCompletionService;
use App\Support\EventPlanCatalog;
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

        return view('organizer.events.index', compact('events'));
    }

    public function create(): View
    {
        abort_unless(request()->user()->canCreateEvents(),403);
        return view('organizer.events.create', [
            'organizerName' => request()->user()->organizerProfile?->organization_name,
            'maxArrows' => request()->user()->hasActiveOrganizerSubscription() ? 180 : 36,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canCreateEvents(),403);
        $validated = $this->validateEvent($request, true);
        $groups = $validated['groups'] ?? [];
        $publish = ($validated['submit_mode'] ?? 'draft') === 'publish';
        unset($validated['groups'], $validated['submit_mode']);
        $validated['status'] = $publish ? 'approved' : 'draft';
        $validated['published_at'] = $publish ? now() : null;
        $validated['verified'] = $publish;

        $subscription = $request->user()->activeOrganizerSubscription();
        if ($subscription) {
            $validated['plan_code'] = EventPlanCatalog::SUBSCRIPTION;
            $validated['plan_status'] = EventPlanCatalog::STATUS_ACTIVE;
            $validated['plan_limits_snapshot'] = EventPlanCatalog::limits(EventPlanCatalog::SUBSCRIPTION);
            $validated['plan_features_snapshot'] = EventPlanCatalog::features(EventPlanCatalog::SUBSCRIPTION);
            $validated['plan_activated_at'] = now();
            $validated['plan_expires_at'] = null;
            $validated['plan_order_reference'] = 'subscription:'.$subscription->id;
        }

        $event = DB::transaction(function () use ($validated, $groups, $request, $publish) {
            $event = Event::create($validated);
            $event->staff()->create([
                'user_id' => $request->user()->id, 'role' => 'owner', 'status' => 'active',
                'invited_by' => $request->user()->id, 'invited_at' => now(), 'accepted_at' => now(),
            ]);
            foreach ($groups as $group) {
                $event->groups()->create($group);
            }
            $this->audit($event, $request, 'event.created');
            if ($publish) {
                $this->audit($event, $request, 'event.published');
            }
            return $event;
        });

        return redirect()->route('organizer.events.show', $event)->with(
            'success',
            $publish ? '賽事已建立並發布，可以立即分享給選手。' : '賽事草稿已建立。'
        );
    }

    public function show(Request $request, Event $event, EventCompletionService $completion): View
    {
        $this->authorize('viewManagement', $event);
        $event->load(['groups' => fn ($query) => $query->withCount('registrations'), 'staff.user'])
            ->loadCount([
                'registrations', 'badges',
                'registrations as active_registrations_count' => fn ($query) => $query
                    ->whereIn('status', ['registered', 'checked_in']),
                'registrations as pending_payments_count' => fn ($query) => $query
                    ->whereIn('status', ['registered', 'checked_in'])
                    ->whereHas('event_group', fn ($group) => $group->where('fee', '>', 0))
                    ->where('payment_status', 'pending'),
                'registrations as verified_results_count' => fn ($query) => $query->whereNotNull('score_verified_at'),
            ]);
        $statusCounts = $event->registrations()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $auditLogs = $request->user()->can('viewAuditLogs', $event)
            ? $event->auditLogs()->with('user')->limit(20)->get()
            : collect();
        $staffInviteQrs = [];
        if ($request->user()->can('manageStaff', $event)) {
            $writer = new Writer(new ImageRenderer(new RendererStyle(280, 2), new SvgImageBackEnd));
            foreach (['manager', 'staff', 'score_manager', 'judge', 'chief_judge', 'volunteer', 'viewer'] as $role) {
                $url = URL::temporarySignedRoute('organizer.staff-invitations.show', now()->addDay(), [
                    'event' => $event, 'role' => $role, 'inviter' => $request->user()->id,
                ]);
                $staffInviteQrs[$role] = 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($url));
            }
        }

        $officiallyCompleted = $event->auditLogs()->where('action', 'event.completed')->exists();
        $completionCheck = $officiallyCompleted
            ? ['ready'=>true, 'blockers'=>[]]
            : $completion->inspect($event);

        return view('organizer.events.show', compact(
            'event', 'statusCounts', 'auditLogs', 'staffInviteQrs', 'officiallyCompleted', 'completionCheck'
        ));
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
        abort_unless(in_array($event->status, ['draft', 'pending', 'rejected'], true), 422);
        abort_if($event->groups()->doesntExist(), 422, '至少建立一個組別才能發布。');
        $event->update(['status' => 'approved', 'review_note' => null, 'published_at' => now(), 'verified' => true]);
        $this->audit($event, $request, 'event.published');

        return back()->with('success', '賽事已發布，參賽者現在可以查看與報名。');
    }

    public function unpublish(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($event->isPublished(), 422, '目前賽事尚未發布。');
        $event->update(['status' => 'draft', 'published_at' => null, 'verified' => false]);
        $this->audit($event, $request, 'event.unpublished');

        return back()->with('success', '賽事已下架並保留為草稿。');
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $event->update(['cancelled_at' => now()]);
        $this->audit($event, $request, 'event.cancelled');

        return back()->with('success', '賽事已取消並停止公開報名。');
    }

    public function complete(
        Request $request,
        Event $event,
        EventCompletionService $completion,
        EventBadgeAwardService $badges,
    ): RedirectResponse {
        $this->authorize('update', $event);
        abort_if($event->cancelled_at, 422, '已取消的賽事不能結案。');
        abort_if($event->auditLogs()->where('action', 'event.completed')->exists(), 422, '此賽事已經完成結案。');

        $completion->complete($event, $request->user()->id);
        $finisherBadges = $badges->awardFinishersFor($event->fresh());

        return back()->with('success', '整場賽事已正式完成，計分設備已停用'.($finisherBadges ? '，並發放 '.$finisherBadges.' 枚完賽 Badge' : '').'。');
    }

    public function addStaff(Request $request, Event $event, EventBadgeAwardService $badges): RedirectResponse
    {
        $this->authorize('manageStaff', $event);
        $validated = $request->validate(['email' => ['required', 'email', 'exists:users,email'], 'role' => ['required', 'in:manager,staff,score_manager,judge,chief_judge,volunteer,viewer']]);
        $user = User::where('email', $validated['email'])->firstOrFail();
        $staff = EventStaff::updateOrCreate(['event_id' => $event->id, 'user_id' => $user->id], [
            'role' => $validated['role'], 'status' => 'active', 'invited_by' => $request->user()->id,
            'invited_at' => now(), 'accepted_at' => now(),
        ]);
        $badges->awardTeamFor($staff);
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
        abort_unless(in_array($role, ['manager', 'staff', 'score_manager', 'judge', 'chief_judge', 'volunteer', 'viewer'], true), 404);
        return view('organizer.events.staff-invitation', compact('event', 'role'));
    }

    public function acceptStaffInvitation(Request $request, Event $event, string $role, EventBadgeAwardService $badges): RedirectResponse
    {
        abort_unless(in_array($role, ['manager', 'staff', 'score_manager', 'judge', 'chief_judge', 'volunteer', 'viewer'], true), 404);
        $inviter = User::findOrFail($request->integer('inviter'));
        abort_unless($inviter->can('manageStaff', $event), 403, '這份邀請已失效。');
        abort_if($event->staff()->where('user_id', $request->user()->id)->where('role', 'owner')->exists(), 422, '賽事擁有者不需要加入邀請。');

        $staff = EventStaff::updateOrCreate(['event_id' => $event->id, 'user_id' => $request->user()->id], [
            'role' => $role, 'status' => 'active', 'invited_by' => $inviter->id,
            'invited_at' => now(), 'accepted_at' => now(),
        ]);
        $badges->awardTeamFor($staff);
        $this->audit($event, $request, 'staff.invitation_accepted', $request->user()->id, ['role' => $role]);

        return redirect()->route('organizer.events.show', $event)->with('success', '已加入 '.$event->name.' 的工作團隊。');
    }

    private function validateEvent(Request $request, bool $creating = false): array
    {
        $maxArrows = $request->user()->hasActiveOrganizerSubscription() ? 180 : 36;
        $maxGroups = $request->user()->hasActiveOrganizerSubscription() ? null : 1;
        $rules = [
            'name' => ['required', 'string', 'max:120'], 'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'], 'mode' => ['required', 'in:indoor,outdoor'],
            'level' => ['nullable', 'string', 'max:50'], 'organizer' => ['required', 'string', 'max:120'],
            'reg_start' => ['nullable', 'date', 'required_with:reg_end'], 'reg_end' => ['nullable', 'date', 'required_with:reg_start', 'after_or_equal:reg_start'],
            'venue' => ['nullable', 'string', 'max:255'], 'map_link' => ['nullable', 'url'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'], 'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ];

        if ($creating) {
            $rules += [
                'submit_mode' => ['nullable', 'in:draft,publish'],
                'groups' => array_filter(['nullable', 'array', 'required_if:submit_mode,publish', 'min:1', $maxGroups === null ? null : 'max:'.$maxGroups]),
                'groups.*.name' => ['required', 'string', 'max:100'],
                'groups.*.bow_type' => ['nullable', 'in:recurve,compound,barebow'],
                'groups.*.gender' => ['required', 'in:male,female,open'],
                'groups.*.age_class' => ['nullable', 'string', 'max:50'],
                'groups.*.distance' => ['nullable', 'string', 'max:50'],
                'groups.*.arrow_count' => [
                    'required', 'integer', 'min:6', 'max:'.$maxArrows, 'multiple_of:6',
                    function (string $attribute, mixed $value, \Closure $fail) use ($maxArrows): void {
                        if ($maxArrows === 36 && (int) $value > 36) {
                            $fail('免費方案最多只能建立 36 箭單局賽事，請先升級單場方案或訂閱。');
                        }
                    },
                ],
                'groups.*.arrows_per_end' => ['required', 'integer', 'in:3,6'],
                'groups.*.quota' => ['nullable', 'integer', 'min:1'],
                'groups.*.fee' => ['nullable', 'integer', 'min:0'],
                'groups.*.is_team' => ['nullable', 'boolean'],
            ];
        }

        return $request->validate($rules);
    }

    private function audit(Event $event, Request $request, string $action, ?int $subjectId = null, array $metadata = []): void
    {
        EventAuditLog::create(['event_id' => $event->id, 'user_id' => $request->user()->id, 'action' => $action, 'subject_type' => $subjectId ? User::class : Event::class, 'subject_id' => $subjectId ?? $event->id, 'metadata' => $metadata]);
    }
}
