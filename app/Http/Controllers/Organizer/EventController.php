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
            'canUseUnlisted' => request()->user()->hasActiveOrganizerSubscription(),
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
        $nextAction = $this->nextAction($request, $event, $officiallyCompleted, $completionCheck);

        return view('organizer.events.show', compact(
            'event', 'statusCounts', 'auditLogs', 'staffInviteQrs', 'officiallyCompleted', 'completionCheck', 'nextAction'
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
        $event = $request->route('event');
        $canUseUnlisted = $creating
            ? $request->user()->hasActiveOrganizerSubscription()
            : $event instanceof Event && $event->hasPlanFeature('unlisted_visibility');
        $rules = [
            'name' => ['required', 'string', 'max:120'], 'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'], 'mode' => ['required', 'in:indoor,outdoor'],
            'level' => ['nullable', 'string', 'max:50'], 'organizer' => ['required', 'string', 'max:120'],
            'reg_start' => ['nullable', 'date', 'required_with:reg_end'], 'reg_end' => ['nullable', 'date', 'required_with:reg_start', 'after_or_equal:reg_start'],
            'venue' => ['nullable', 'string', 'max:255'], 'map_link' => ['nullable', 'url'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'], 'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'visibility' => [
                'nullable', 'in:public,unlisted',
                function (string $attribute, mixed $value, \Closure $fail) use ($canUseUnlisted): void {
                    if ($value === 'unlisted' && ! $canUseUnlisted) {
                        $fail('不公開賽事為單場升級或訂閱方案功能。');
                    }
                },
            ],
            'check_in_enabled' => ['nullable', 'boolean'],
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

        $validated = $request->validate($rules);
        $canUseCheckIn = $creating
            ? $request->user()->hasActiveOrganizerSubscription()
            : $event instanceof Event && $event->hasPlanFeature('check_in');
        $validated['check_in_enabled'] = $canUseCheckIn && $request->boolean('check_in_enabled');

        return $validated;
    }

    /** @param array{ready:bool,blockers:array<int,string>} $completionCheck */
    private function nextAction(Request $request, Event $event, bool $officiallyCompleted, array $completionCheck): ?array
    {
        if ($officiallyCompleted || $event->cancelled_at) return null;

        if (! $event->isPublished() && $request->user()->can('update', $event)) {
            if ($event->groups->isEmpty() && $request->user()->can('manageGroups', $event)) {
                return ['title'=>'先建立參賽組別', 'description'=>'至少需要一個組別，才能發布賽事並讓選手報名。', 'label'=>'建立組別', 'url'=>route('events.groups.index', $event)];
            }
            return ['title'=>'發布賽事並開放報名', 'description'=>'資料確認完成後發布，選手即可從公開頁選擇組別報名。', 'label'=>'發布賽事', 'method'=>'POST', 'url'=>route('organizer.events.submit', $event)];
        }

        $hasScoring = $event->scoringSessions()->exists();
        if (! $hasScoring) {
            if ($event->active_registrations_count === 0 && $request->user()->can('manageRegistrations', $event)) {
                return ['title'=>'先讓選手完成報名', 'description'=>'目前尚無有效報名；分享賽事或進入名單頁確認報名狀況。', 'label'=>'查看報名名單', 'url'=>route('organizer.events.registrations.index', $event)];
            }
            $hasUnreported = $event->registrations()->where('status', 'registered')->whereNull('checked_in_at')->exists();
            if ($event->requiresCheckIn() && $hasUnreported && $request->user()->can('manageRegistrations', $event)) {
                return ['title'=>'完成現場報到，再確認排靶', 'description'=>'尚未報到的選手會在排靶時標記為 DNS；先確認現場名單可避免誤判。', 'label'=>'前往現場報到', 'url'=>route('organizer.events.check-in.index', $event)];
            }
            if ($request->user()->can('manageScores', $event)) {
                return ['title'=>'確認出賽名單並完成排靶', 'description'=>'勾選實際出賽選手後，系統會一次分配所有組別靶位並停止報名。', 'label'=>'確認名單與排靶', 'url'=>route('organizer.events.scoring.index', $event)];
            }
        }

        $unfinishedTargets = $event->scoringSessions()->with('targets')->get()->flatMap->targets
            ->whereNotIn('status', ['completed', 'dns'])->count();
        if ($unfinishedTargets > 0 && $request->user()->can('manageScores', $event)) {
            return ['title'=>'完成現場計分', 'description'=>'還有 '.$unfinishedTargets.' 個靶位尚未完成；可查看設備綁定與各靶進度。', 'label'=>'查看計分進度', 'url'=>route('organizer.events.scoring.index', $event)];
        }

        if (! $completionCheck['ready'] && $request->user()->can('viewResults', $event)) {
            return ['title'=>'核對並發布各組成績', 'description'=>'計分結束後依序完成必要的裁判簽核、成績核准與正式發布。', 'label'=>'前往成績管理', 'url'=>route('organizer.events.results.index', $event)];
        }

        if ($completionCheck['ready'] && $request->user()->can('update', $event)) {
            return ['title'=>'所有必要流程已完成', 'description'=>'正式結案後會停用所有計分設備，已發布成績仍會保留。', 'label'=>'完成整場賽事', 'method'=>'POST', 'url'=>route('organizer.events.complete', $event)];
        }

        return null;
    }

    private function audit(Event $event, Request $request, string $action, ?int $subjectId = null, array $metadata = []): void
    {
        EventAuditLog::create(['event_id' => $event->id, 'user_id' => $request->user()->id, 'action' => $action, 'subject_type' => $subjectId ? User::class : Event::class, 'subject_id' => $subjectId ?? $event->id, 'metadata' => $metadata]);
    }
}
