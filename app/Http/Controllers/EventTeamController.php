<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\EventTeam;
use App\Models\EventTeamMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EventTeamController extends Controller
{
    public function index(Request $request, Event $event, EventGroup $group): View
    {
        $this->assertGroup($event, $group);
        abort_unless($event->isPublished() && $group->is_team, 404);

        $myRegistration = $this->registrationFor($request, $event, $group, false);
        $teams = $group->eventTeams()->where('status', '!=', 'disbanded')
            ->with(['captainRegistration', 'memberships.registration.scoreEntries'])
            ->orderByRaw("CASE status WHEN 'recruiting' THEN 0 WHEN 'full' THEN 1 ELSE 2 END")
            ->orderBy('name')->get();

        if (! $group->teamFormationIsOpen()) {
            $group->eventTeams()->whereIn('status', ['recruiting', 'full'])->update(['status'=>'locked', 'locked_at'=>now()]);
            $teams->each(fn (EventTeam $team) => $team->status = 'locked');
        }

        $eligibleInvitees = collect();
        $myTeam = null;
        $myMembership = null;
        if ($myRegistration) {
            $myMembership = EventTeamMember::with('team')->where('event_registration_id', $myRegistration->id)->first();
            $myTeam = $myMembership?->status === 'active' ? $myMembership->team : null;
            if ($myTeam?->captain_registration_id === $myRegistration->id && $group->teamFormationIsOpen()) {
                $occupiedIds = EventTeamMember::where('event_group_id', $group->id)->pluck('event_registration_id');
                $eligibleInvitees = $group->registrations()->whereIn('status', ['registered', 'checked_in'])
                    ->whereNotIn('id', $occupiedIds)->orderBy('name')->get();
            }
        }

        $rankings = $teams->map(function (EventTeam $team): array {
            $members = $team->memberships->where('status', 'active')->whereIn('role', ['captain','member']);
            $published = $members->count() === $team->requiredSize()
                && $members->every(fn (EventTeamMember $member) => $member->registration?->result_published_at !== null);
            $scores = $members->flatMap(fn (EventTeamMember $member) => $member->registration?->scoreEntries ?? collect());
            $arrows = $scores->flatMap(fn ($entry) => $entry->scores ?? []);
            return ['team'=>$team, 'published'=>$published, 'total'=>$published ? $scores->sum('end_total') : null,
                'ten_count'=>$published ? $arrows->filter(fn ($score) => (string) $score === '10')->count() : null,
                'x_count'=>$published ? $arrows->filter(fn ($score) => strtoupper((string) $score) === 'X')->count() : null];
        })->filter(fn ($row) => $row['published'])->sort(fn ($a, $b) => [$b['total'],$b['ten_count'],$b['x_count']] <=> [$a['total'],$a['ten_count'],$a['x_count']])->values();

        $canManage = $request->user()->can('manageRegistrations', $event);
        return view('events.teams.index', compact('event', 'group', 'teams', 'myRegistration', 'myMembership', 'myTeam', 'eligibleInvitees', 'rankings', 'canManage'));
    }

    public function store(Request $request, Event $event, EventGroup $group): RedirectResponse
    {
        $this->assertOpen($event, $group);
        $registration = $this->registrationFor($request, $event, $group);
        $format = $request->validate(['team_format'=>['nullable','in:standard,mixed']])['team_format'] ?? ($group->hasTeamFormat('standard') ? 'standard' : 'mixed');
        abort_unless($group->hasTeamFormat($format), 422, '此組別未開放所選團體形式。');
        if ($format==='mixed') abort_unless($registration->athlete_gender,422,'混雙選手必須先在報名資料設定性別。');
        $data = $request->validate([
            'name'=>['required','string','max:100'],
            'recruitment_note'=>['nullable','string','max:300'],
        ]);

        DB::transaction(function () use ($event, $group, $registration, $data, $request, $format): void {
            abort_if(EventTeamMember::where('event_group_id', $group->id)->where('event_registration_id', $registration->id)->exists(), 422, '你已經有組隊申請或隊伍。');
            $team = EventTeam::create(['event_id'=>$event->id, 'event_group_id'=>$group->id,
                'captain_registration_id'=>$registration->id, 'name'=>$data['name'], 'team_format'=>$format, 'is_open'=>true,
                'recruitment_note'=>$data['recruitment_note'] ?? null, 'status'=>'recruiting']);
            $team->memberships()->create(['event_group_id'=>$group->id, 'event_registration_id'=>$registration->id,
                'role'=>'captain', 'status'=>'active', 'requested_by'=>$request->user()->id, 'responded_at'=>now()]);
        });
        return back()->with('success', '公開招募隊伍已建立，其他選手現在可以申請加入。');
    }

    public function apply(Request $request, Event $event, EventGroup $group, EventTeam $team): RedirectResponse
    {
        $this->assertTeam($event, $group, $team); $this->assertOpen($event, $group);
        $registration = $this->registrationFor($request, $event, $group);
        abort_unless($team->status === 'recruiting' && $team->is_open, 422, '此隊伍目前不接受申請。');
        abort_if(EventTeamMember::where('event_group_id',$group->id)->where('event_registration_id',$registration->id)->exists(), 422, '你已經有組隊申請或隊伍。');
        if ($team->team_format === 'mixed') {
            abort_unless($registration->athlete_gender, 422, '混雙選手必須先設定性別。');
            abort_if($team->competingMemberships()->with('registration')->get()
                ->contains(fn (EventTeamMember $member) => $member->registration?->athlete_gender === $registration->athlete_gender),
                422, '此混雙隊伍目前需要另一性別的選手。');
        }
        $team->memberships()->create(['event_group_id'=>$group->id, 'event_registration_id'=>$registration->id,
            'role'=>'member', 'status'=>'pending', 'requested_by'=>$request->user()->id]);
        return back()->with('success', '已送出加入申請。');
    }

    public function invite(Request $request, Event $event, EventGroup $group, EventTeam $team): RedirectResponse
    {
        $this->assertCaptain($request, $event, $group, $team); $this->assertOpen($event, $group);
        $data=$request->validate(['registration_id'=>['required','integer'],'member_role'=>['nullable','in:member,substitute']]);
        $role=$data['member_role'] ?? 'member';
        abort_if($role==='member' && $team->competingMemberships()->count() >= $team->requiredSize(), 422, '正式隊員人數已滿。');
        abort_if($role==='substitute' && ($group->team_substitute_limit < 1 || $team->activeMemberships()->where('role','substitute')->count() >= $group->team_substitute_limit),422,'候補名額已滿或未開放。');
        $registration=$group->registrations()->whereIn('status',['registered','checked_in'])->findOrFail($data['registration_id']);
        abort_if(EventTeamMember::where('event_group_id',$group->id)->where('event_registration_id',$registration->id)->exists(), 422, '此選手已有隊伍或邀請。');
        $team->memberships()->create(['event_group_id'=>$group->id,'event_registration_id'=>$registration->id,
            'role'=>$role,'status'=>'invited','requested_by'=>$request->user()->id]);
        return back()->with('success','邀請已送出。');
    }

    public function respond(Request $request, Event $event, EventGroup $group, EventTeamMember $membership): RedirectResponse
    {
        $this->assertGroup($event,$group); $this->assertOpen($event,$group);
        $registration=$this->registrationFor($request,$event,$group);
        abort_unless($membership->event_group_id===$group->id && $membership->event_registration_id===$registration->id && $membership->status==='invited',403);
        return $this->resolveMembership($request,$membership,$request->input('decision')==='accept');
    }

    public function review(Request $request, Event $event, EventGroup $group, EventTeam $team, EventTeamMember $membership): RedirectResponse
    {
        $this->assertCaptain($request,$event,$group,$team); $this->assertOpen($event,$group);
        abort_unless($membership->event_team_id===$team->id && $membership->status==='pending',404);
        return $this->resolveMembership($request,$membership,$request->input('decision')==='approve');
    }

    public function leave(Request $request, Event $event, EventGroup $group, EventTeamMember $membership): RedirectResponse
    {
        $this->assertGroup($event,$group); $this->assertOpen($event,$group);
        $registration=$this->registrationFor($request,$event,$group);
        abort_unless($membership->event_registration_id===$registration->id,403);
        $team=$membership->team;
        if ($membership->role==='captain') {
            $team->update(['status'=>'disbanded']); $team->memberships()->delete();
            return back()->with('success','隊伍已解散。');
        }
        $membership->delete(); $team->refreshStatus();
        return back()->with('success','已退出隊伍或取消申請。');
    }

    private function resolveMembership(Request $request, EventTeamMember $membership, bool $accept): RedirectResponse
    {
        $team=$membership->team()->with('group')->firstOrFail();
        if ($accept && $membership->role!=='substitute') {
            abort_if($team->competingMemberships()->count() >= $team->requiredSize(),422,'隊伍人數已滿。');
            if ($team->team_format==='mixed') {
                $gender=$membership->registration?->athlete_gender;
                abort_unless($gender,422,'混雙選手必須先設定性別。');
                abort_if($team->competingMemberships()->with('registration')->get()->contains(fn ($member)=>$member->registration?->athlete_gender===$gender),422,'混雙隊伍必須由一男一女組成。');
            }
        }
        if ($accept && $membership->role==='substitute') abort_if($team->activeMemberships()->where('role','substitute')->count() >= $team->group->team_substitute_limit,422,'候補名額已滿。');
        if ($accept) $membership->update(['status'=>'active','responded_at'=>now()]); else $membership->delete();
        $team->refreshStatus();
        return back()->with('success',$accept?'已加入隊伍。':'已拒絕或取消。');
    }

    private function registrationFor(Request $request, Event $event, EventGroup $group, bool $required=true): ?EventRegistration
    {
        $registration=EventRegistration::where('event_id',$event->id)->where('event_group_id',$group->id)
            ->where('user_id',$request->user()->id)->whereIn('status',['registered','checked_in'])->first();
        if ($required) abort_unless($registration,403,'只有已報名此組別的選手可以組隊。');
        return $registration;
    }
    private function assertGroup(Event $event, EventGroup $group): void { abort_unless($group->event_id===$event->id,404); }
    private function assertOpen(Event $event, EventGroup $group): void { $this->assertGroup($event,$group); abort_unless($group->is_team && $group->teamFormationIsOpen(),422,'目前不在組隊期間。'); }
    private function assertTeam(Event $event, EventGroup $group, EventTeam $team): void { $this->assertGroup($event,$group); abort_unless($team->event_id===$event->id && $team->event_group_id===$group->id,404); }
    private function assertCaptain(Request $request, Event $event, EventGroup $group, EventTeam $team): void { $this->assertTeam($event,$group,$team); $registration=$this->registrationFor($request,$event,$group); abort_unless($team->captain_registration_id===$registration->id,403); }

    public function autoMatch(Request $request, Event $event, EventGroup $group): RedirectResponse
    {
        $this->assertOpen($event,$group);
        $this->authorize('manageRegistrations',$event);
        $occupied=EventTeamMember::where('event_group_id',$group->id)->pluck('event_registration_id');
        $format=$request->validate(['team_format'=>['nullable','in:standard,mixed']])['team_format'] ?? ($group->hasTeamFormat('standard') ? 'standard' : 'mixed');
        abort_unless($group->hasTeamFormat($format),422,'此組別未開放所選團體形式。');
        $available=$group->registrations()->whereIn('status',['registered','checked_in'])->whereNotIn('id',$occupied)->orderBy('id')->get();
        $sets=$format==='mixed'
            ? $available->where('athlete_gender','male')->values()->zip($available->where('athlete_gender','female')->values())->filter(fn($pair)=>$pair->filter()->count()===2)
            : $available->chunk(3)->filter(fn($chunk)=>$chunk->count()===3);
        $created=0;
        DB::transaction(function () use ($sets,$event,$group,$request,$format,&$created): void {
            foreach ($sets as $members) {
                $members=collect($members)->values(); $captain=$members->first();
                $team=EventTeam::create(['event_id'=>$event->id,'event_group_id'=>$group->id,'captain_registration_id'=>$captain->id,'team_format'=>$format,'name'=>'自動配對 '.str_pad((string)($group->eventTeams()->count()+1),2,'0',STR_PAD_LEFT),'status'=>'full']);
                foreach ($members as $index=>$registration) $team->memberships()->create(['event_group_id'=>$group->id,'event_registration_id'=>$registration->id,'role'=>$index===0?'captain':'member','status'=>'active','requested_by'=>$request->user()->id,'responded_at'=>now()]);
                $created++;
            }
        });
        return back()->with('success',$created ? '已自動建立 '.$created.' 支完整隊伍。' : '目前沒有足夠的未組隊選手可配對。');
    }
}
