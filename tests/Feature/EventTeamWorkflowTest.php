<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\EventScoreEntry;
use App\Models\EventStaff;
use App\Models\EventTeam;
use App\Models\EventTeamMember;
use App\Models\User;
use App\Support\EventPlanCatalog;
use App\Services\QualificationRankingSnapshotService;
use App\Services\RecurveSetMatchService;
use App\Services\TeamEliminationBracketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTeamWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_archers_can_form_a_three_person_event_team(): void
    {
        [$event,$group,$users,$registrations]=$this->teamEvent(3);

        $this->actingAs($users[0])->post(route('events.teams.store',[$event,$group]), ['name'=>'團體 A','recruitment_note'=>'尋找同校隊友'])->assertSessionHas('success');
        $team=EventTeam::firstOrFail();
        $this->assertSame($registrations[0]->id,$team->captain_registration_id);
        $this->assertTrue($team->is_open);
        $this->assertSame('尋找同校隊友',$team->recruitment_note);
        $this->assertDatabaseHas('event_team_members',['event_team_id'=>$team->id,'event_registration_id'=>$registrations[0]->id,'role'=>'captain','status'=>'active']);

        $this->actingAs($users[1])->post(route('events.teams.apply',[$event,$group,$team]))->assertSessionHas('success');
        $application=EventTeamMember::where('event_registration_id',$registrations[1]->id)->firstOrFail();
        $this->actingAs($users[0])->get(route('events.teams.index',[$event,$group]))->assertOk()->assertSee('待審核申請')->assertSee('同意加入');
        $this->actingAs($users[0])->patch(route('events.teams.review',[$event,$group,$team,$application]),['decision'=>'approve'])->assertSessionHas('success');

        $this->actingAs($users[0])->post(route('events.teams.invite',[$event,$group,$team]),['registration_id'=>$registrations[2]->id])->assertSessionHas('success');
        $invitation=EventTeamMember::where('event_registration_id',$registrations[2]->id)->firstOrFail();
        $this->actingAs($users[2])->patch(route('events.teams.respond',[$event,$group,$invitation]),['decision'=>'accept'])->assertSessionHas('success');

        $this->assertSame('full',$team->fresh()->status);
        $this->assertSame(3,$team->activeMemberships()->count());
        $this->actingAs($users[0])->get(route('events.teams.index',[$event,$group]))->assertOk()->assertSee('3 / 3');
    }

    public function test_only_registered_archer_can_create_team_and_one_archer_cannot_join_two(): void
    {
        [$event,$group,$users]=$this->teamEvent(2);
        $outsider=User::factory()->create(['profile_completed_at'=>now()]);
        $this->actingAs($outsider)->post(route('events.teams.store',[$event,$group]),['name'=>'非法隊伍'])->assertForbidden();

        $this->actingAs($users[0])->post(route('events.teams.store',[$event,$group]),['name'=>'團體 A']);
        $this->actingAs($users[1])->post(route('events.teams.store',[$event,$group]),['name'=>'團體 B']);
        $first=EventTeam::where('name','團體 A')->firstOrFail();
        $this->actingAs($users[1])->post(route('events.teams.apply',[$event,$group,$first]))->assertStatus(422);
    }

    public function test_team_ranking_uses_published_individual_scores_and_registration_withdrawal_updates_team(): void
    {
        [$event,$group,$users,$registrations]=$this->teamEvent(3);
        $this->actingAs($users[0])->post(route('events.teams.store',[$event,$group]),['name'=>'滿分隊']);
        $team=EventTeam::firstOrFail();
        foreach ([1,2] as $index) {
            $team->memberships()->create(['event_group_id'=>$group->id,'event_registration_id'=>$registrations[$index]->id,'role'=>'member','status'=>'active','requested_by'=>$users[0]->id,'responded_at'=>now()]);
        }
        $team->refreshStatus();
        foreach ($registrations as $index=>$registration) {
            $registration->update(['result_published_at'=>now()]);
            EventScoreEntry::create(['event_id'=>$event->id,'event_registration_id'=>$registration->id,'user_id'=>$users[$index]->id,'end_number'=>1,'scores'=>['X','10','9'],'end_total'=>29]);
        }

        $this->actingAs($users[0])->get(route('events.teams.index',[$event,$group]))
            ->assertOk()->assertSee('團體正式排名')->assertSee('87')->assertSee('滿分隊');

        $this->actingAs($users[1])->patch(route('event-registrations.withdraw',$registrations[1]));
        $this->assertSame('recruiting',$team->fresh()->status);
        $this->assertDatabaseMissing('event_team_members',['event_registration_id'=>$registrations[1]->id]);
    }

    public function test_organizer_can_auto_match_mixed_teams_by_gender(): void
    {
        [$event,$group,$users,$registrations]=$this->teamEvent(4);
        $group->update(['team_type'=>'mixed','team_size'=>2]);
        foreach ($registrations as $index=>$registration) {
            $registration->update(['athlete_gender'=>$index < 2 ? 'male' : 'female']);
        }

        $owner=User::factory()->create(['profile_completed_at'=>now()]);
        EventStaff::create(['event_id'=>$event->id,'user_id'=>$owner->id,'role'=>'owner','status'=>'active','invited_by'=>$owner->id]);
        $this->actingAs($owner)->post(route('events.teams.auto-match',[$event,$group]))
            ->assertSessionHas('success');

        $this->assertSame(2,EventTeam::count());
        foreach (EventTeam::with('memberships.registration')->get() as $team) {
            $this->assertEqualsCanonicalizing(['female','male'],$team->memberships->pluck('registration.athlete_gender')->all());
        }
    }

    public function test_team_bracket_uses_team_seeds_and_six_arrow_set_scoring(): void
    {
        [$event,$group,$users,$registrations]=$this->teamEvent(12);
        foreach ($registrations as $index=>$registration) {
            $registration->update(['score_verified_at'=>now(),'result_published_at'=>now(),'result_status'=>'completed']);
            EventScoreEntry::create(['event_id'=>$event->id,'event_registration_id'=>$registration->id,'user_id'=>$users[$index]->id,'end_number'=>1,'scores'=>['10'],'end_total'=>60-$index]);
        }
        foreach ([0,3,6,9] as $start) {
            $team=EventTeam::create(['event_id'=>$event->id,'event_group_id'=>$group->id,'captain_registration_id'=>$registrations[$start]->id,'name'=>'團體 '.($start+1),'status'=>'full']);
            foreach (range($start,$start+2) as $index) $team->memberships()->create(['event_group_id'=>$group->id,'event_registration_id'=>$registrations[$index]->id,'role'=>$index===$start?'captain':'member','status'=>'active','requested_by'=>$users[$start]->id,'responded_at'=>now()]);
        }
        $group->qualificationPhase()->firstOrFail()->update(['status'=>'published','locked_at'=>now(),'published_at'=>now()]);
        app(QualificationRankingSnapshotService::class)->capture($event,$group);

        $bracket=app(TeamEliminationBracketService::class)->create($event,$group,4,false,$event->user_id);
        $match=$bracket->matches()->where('round_number',1)->whereNotNull('participant_one_team_id')->whereNotNull('participant_two_team_id')->firstOrFail();
        $updated=app(RecurveSetMatchService::class)->recordSet($match,['10','10','10','10','10','10'],['9','9','9','9','9','9'],null);

        $this->assertSame('team',$bracket->category);
        $this->assertSame(60,$updated->sets->first()->participant_one_total);
        $this->assertNotNull($updated->participant_one_team_id);
    }

    public function test_group_can_offer_standard_and_mixed_but_archer_can_choose_only_one(): void
    {
        [$event,$group,$users,$registrations]=$this->teamEvent(4);
        $group->update(['standard_team_enabled'=>true,'mixed_team_enabled'=>true]);
        foreach ($registrations as $index=>$registration) $registration->update(['athlete_gender'=>$index % 2 === 0 ? 'male' : 'female']);

        $this->actingAs($users[0])->post(route('events.teams.store',[$event,$group]),['team_format'=>'standard','name'=>'三人隊'])->assertSessionHas('success');
        $this->actingAs($users[0])->post(route('events.teams.store',[$event,$group]),['team_format'=>'mixed','name'=>'重複混雙'])->assertStatus(422);
        $this->actingAs($users[1])->post(route('events.teams.store',[$event,$group]),['team_format'=>'mixed','name'=>'混雙隊'])->assertSessionHas('success');

        $this->assertDatabaseHas('event_teams',['event_group_id'=>$group->id,'name'=>'三人隊','team_format'=>'standard']);
        $this->assertDatabaseHas('event_teams',['event_group_id'=>$group->id,'name'=>'混雙隊','team_format'=>'mixed']);
        $this->actingAs($users[2])->get(route('events.teams.index',[$event,$group]))
            ->assertOk()->assertSee('3人團體')->assertSee('男女混雙');
    }

    private function teamEvent(int $count): array
    {
        $event=Event::factory()->create([
            'plan_code'=>EventPlanCatalog::EVENT_PASS,
            'plan_features_snapshot'=>EventPlanCatalog::features(EventPlanCatalog::EVENT_PASS),
            'plan_limits_snapshot'=>EventPlanCatalog::limits(EventPlanCatalog::EVENT_PASS),
            'reg_start'=>now()->subDay(),'reg_end'=>now()->addWeek(),
        ]);
        $group=EventGroup::factory()->create(['event_id'=>$event->id,'bow_type'=>'recurve','is_team'=>true,'team_size'=>3,'team_formation_end'=>now()->addWeek()]);
        $users=User::factory()->count($count)->create(['profile_completed_at'=>now()]);
        $registrations=$users->map(fn (User $user)=>EventRegistration::create([
            'event_id'=>$event->id,'event_group_id'=>$group->id,'user_id'=>$user->id,
            'name'=>$user->name,'email'=>$user->email,'status'=>'registered',
        ]));
        return [$event,$group,$users,$registrations];
    }
}
