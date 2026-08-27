<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\EventScoreEntry;
use App\Models\EventStaff;
use App\Models\EventScoringSession;
use App\Models\EventScoringTarget;
use App\Models\User;
use App\Models\OrganizerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EventManagementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_organizer_can_publish_event_without_platform_review(): void
    {
        $owner = User::factory()->create();
        OrganizerProfile::create(['user_id'=>$owner->id,'organization_name'=>'測試主辦方','organization_type'=>'club','contact_name'=>$owner->name,'contact_email'=>$owner->email,'contact_phone'=>'0912345678','application_reason'=>'測試','status'=>'approved','approved_at'=>now()]);
        $response = $this->actingAs($owner)->post(route('organizer.events.store'), $this->eventPayload());
        $event = Event::firstOrFail();
        $response->assertRedirect(route('organizer.events.show',$event));
        $this->app['auth']->logout();
        $this->get(route('events.show',$event))->assertNotFound();

        EventGroup::factory()->create(['event_id'=>$event->id]);
        $this->actingAs($owner)->post(route('organizer.events.submit',$event))->assertSessionHas('success');
        $this->assertTrue($event->fresh()->isPublished());
        $this->get(route('events.show',$event))->assertOk();

        $this->actingAs($owner)->post(route('organizer.events.unpublish',$event))->assertSessionHas('success');
        $this->assertSame('draft',$event->fresh()->status);
        $this->app['auth']->logout();
        $this->get(route('events.show',$event))->assertNotFound();
    }

    public function test_approved_organizer_can_create_group_and_publish_in_one_step(): void
    {
        $owner = User::factory()->create();
        OrganizerProfile::create([
            'user_id'=>$owner->id, 'organization_name'=>'快速弓社', 'organization_type'=>'club',
            'contact_name'=>$owner->name, 'contact_email'=>$owner->email, 'contact_phone'=>'0912345678',
            'application_reason'=>'測試', 'status'=>'approved', 'approved_at'=>now(),
        ]);

        $response = $this->actingAs($owner)->post(route('organizer.events.store'), array_merge($this->eventPayload(), [
            'submit_mode'=>'publish',
            'groups'=>[[
                'name'=>'反曲弓公開組', 'bow_type'=>'recurve', 'gender'=>'open', 'distance'=>'70m',
                'arrow_count'=>36, 'arrows_per_end'=>6, 'quota'=>32, 'fee'=>500, 'is_team'=>0,
            ]],
        ]));

        $event = Event::firstOrFail();
        $response->assertRedirect(route('organizer.events.show', $event));
        $this->assertTrue($event->isPublished());
        $this->assertDatabaseHas('event_groups', [
            'event_id'=>$event->id, 'name'=>'反曲弓公開組', 'arrow_count'=>36, 'quota'=>32,
        ]);
    }

    public function test_event_roles_control_management_access(): void
    {
        $owner = User::factory()->create();
        $staffUser = User::factory()->create();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        $event = Event::factory()->create();
        foreach ([[$owner,'owner'],[$staffUser,'staff'],[$viewer,'viewer']] as [$user,$role]) {
            EventStaff::create(['event_id'=>$event->id,'user_id'=>$user->id,'role'=>$role,'status'=>'active','invited_by'=>$owner->id]);
        }

        $this->actingAs($owner)->get(route('organizer.events.edit',$event))->assertOk();
        $this->actingAs($staffUser)->get(route('organizer.events.registrations.index',$event))->assertOk();
        $this->actingAs($viewer)->get(route('organizer.events.registrations.index',$event))->assertForbidden();
        $this->actingAs($owner)->get(route('organizer.events.show',$event))->assertOk()->assertSee('最近操作紀錄');
        $this->actingAs($staffUser)->get(route('organizer.events.show',$event))->assertOk()->assertDontSee('最近操作紀錄');
        $this->actingAs($viewer)->get(route('organizer.events.show',$event))->assertOk()->assertDontSee('最近操作紀錄');
        $this->actingAs($outsider)->get(route('organizer.events.show',$event))->assertForbidden();
    }

    public function test_admin_can_emergency_publish_or_unpublish_with_a_reason(): void
    {
        $admin = User::factory()->create(['is_admin'=>true]);
        $event = Event::factory()->create(['status'=>'draft','verified'=>false,'published_at'=>null]);

        $this->actingAs($admin)->post(route('admin.events.review',$event), [
            'decision'=>'publish', 'review_note'=>'協助主辦方排除發布異常',
        ])->assertSessionHas('success');
        $this->assertTrue($event->fresh()->isPublished());

        $this->actingAs($admin)->post(route('admin.events.review',$event), [
            'decision'=>'unpublish', 'review_note'=>'主辦方回報需緊急下架修正',
        ])->assertSessionHas('success');
        $this->assertSame('draft',$event->fresh()->status);
        $this->assertDatabaseHas('event_audit_logs', [
            'event_id'=>$event->id, 'action'=>'admin.event_force_unpublished',
        ]);
    }

    public function test_staff_can_check_in_member_by_uuid_and_audit_is_recorded(): void
    {
        [$owner,$event,$group] = $this->ownedEvent();
        $member = User::factory()->create();
        $registration = $this->registration($event,$group,$member);

        $this->actingAs($owner)->post(route('organizer.events.registrations.check-in',$event),['uuid'=>$member->uuid])->assertSessionHas('success');
        $this->assertSame('checked_in',$registration->fresh()->status);
        $this->assertNotNull($registration->fresh()->checked_in_at);
        $this->assertDatabaseHas('event_audit_logs',['event_id'=>$event->id,'action'=>'registration.checked_in']);
    }

    public function test_registration_management_starts_with_group_summary_then_opens_group_members(): void
    {
        [$owner, $event, $group] = $this->ownedEvent();
        $group->update(['name'=>'反曲公開組', 'fee'=>500, 'quota'=>24]);
        $member = User::factory()->create(['name'=>'測試選手']);
        $registration = $this->registration($event, $group, $member);

        $this->actingAs($owner)
            ->get(route('organizer.events.registrations.index', $event))
            ->assertOk()
            ->assertSee('選擇組別')
            ->assertSee('反曲公開組')
            ->assertDontSee('測試選手');

        $this->actingAs($owner)
            ->get(route('organizer.events.registrations.index', [$event, 'event_group_id'=>$group->id, 'q'=>'測試選手']))
            ->assertOk()
            ->assertSee('搜尋此組選手')
            ->assertSee('測試選手')
            ->assertSee('標記為繳費完成');

        $this->actingAs($owner)->patch(route('organizer.events.registrations.payment', $event), [
            'registration_ids'=>[$registration->id], 'payment_status'=>'paid', 'payment_amount'=>500,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('event_registrations', [
            'id'=>$registration->id, 'payment_status'=>'paid', 'paid'=>true,
        ]);
    }

    public function test_member_can_accept_a_signed_staff_qr_invitation(): void
    {
        [$owner,$event] = $this->ownedEvent();
        $member = User::factory()->create();
        $url = URL::temporarySignedRoute('organizer.staff-invitations.show', now()->addDay(), [
            'event'=>$event, 'role'=>'staff', 'inviter'=>$owner->id,
        ]);

        $this->actingAs($member)->get($url)->assertOk()->assertSee('確認加入工作團隊');
        $this->actingAs($member)->post($url)->assertRedirect(route('organizer.events.show',$event));
        $this->assertDatabaseHas('event_staff', [
            'event_id'=>$event->id, 'user_id'=>$member->id, 'role'=>'staff', 'status'=>'active',
        ]);
        $this->assertDatabaseHas('event_audit_logs', [
            'event_id'=>$event->id, 'user_id'=>$member->id, 'action'=>'staff.invitation_accepted',
        ]);
    }

    public function test_staff_qr_invitation_rejects_a_tampered_role(): void
    {
        [$owner,$event] = $this->ownedEvent();
        $member = User::factory()->create();
        $url = URL::temporarySignedRoute('organizer.staff-invitations.show', now()->addDay(), [
            'event'=>$event, 'role'=>'staff', 'inviter'=>$owner->id,
        ]);
        $tamperedUrl = str_replace('/staff?', '/manager?', $url);

        $this->actingAs($member)->get($tamperedUrl)->assertForbidden();
        $this->assertDatabaseMissing('event_staff', ['event_id'=>$event->id, 'user_id'=>$member->id]);
    }

    public function test_scores_are_bound_to_registration_and_require_verification_before_publication(): void
    {
        [$owner,$event,$group] = $this->ownedEvent();
        $member = User::factory()->create();
        $registration = $this->registration($event,$group,$member);
        $registration->update(['score_submitted_at'=>now()]);
        EventScoreEntry::create(['event_id'=>$event->id,'event_registration_id'=>$registration->id,'user_id'=>$member->id,'end_number'=>1,'scores'=>[10,10,9,9,8,8],'end_total'=>54]);

        $scoreManager = User::factory()->create();
        EventStaff::create(['event_id'=>$event->id,'user_id'=>$scoreManager->id,'role'=>'score_manager','status'=>'active','invited_by'=>$owner->id]);

        $this->actingAs($owner)->post(route('organizer.events.results.verify',$event),['registration_ids'=>[$registration->id]])->assertForbidden();
        $this->actingAs($scoreManager)->post(route('organizer.events.results.verify',$event),['registration_ids'=>[$registration->id]])->assertSessionHas('success');
        $this->assertNotNull($registration->fresh()->score_verified_at);
        $this->createScoringTarget($event, $group, $owner);
        $this->actingAs($owner)->post(route('organizer.events.results.publish',[$event,$group]))->assertSessionHas('success');
        $this->assertNotNull($registration->fresh()->result_published_at);
        $this->assertNull($event->fresh()->completed_at);
        $phase = $group->qualificationPhase()->firstOrFail();
        $this->assertSame('published', $phase->status);
        $this->assertNotNull($phase->locked_at);
        $this->assertNotNull($phase->published_at);
        $snapshot = $phase->rankingSnapshots()->with('entries')->firstOrFail();
        $this->assertSame(1, $snapshot->version);
        $this->assertSame($registration->id, $snapshot->entries->first()->event_registration_id);

        $target = EventScoringTarget::whereHas('session', fn ($query) => $query->where('event_id', $event->id))->firstOrFail();
        $oldAccessToken = $target->access_token;
        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertDontSee('排名賽戰況');
        $this->app['auth']->logout();
        $this->get(route('events.live', $event))->assertNotFound();
        $this->actingAs($owner)->patch(route('organizer.events.results.live-visibility', [$event, $group]), [
            'visible' => 1,
        ])->assertSessionHas('success');
        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('排名賽戰況')
            ->assertDontSee('即時戰況');
        $this->actingAs($owner)
            ->get(route('organizer.events.show', $event))
            ->assertOk()
            ->assertSee('完成整場賽事');
        $this->actingAs($owner)
            ->post(route('organizer.events.complete', $event))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($event->fresh()->completed_at);
        $this->assertDatabaseHas('event_audit_logs', ['event_id'=>$event->id, 'action'=>'event.completed']);
        $this->assertNotSame($oldAccessToken, $target->fresh()->access_token);
        $this->get(route('scoring-stations.show', $target->fresh()->access_token))->assertStatus(410);
        $this->get(route('events.show', $event->fresh()))
            ->assertOk()
            ->assertSee('排名賽結果');
    }

    public function test_organizer_can_create_shared_target_station_and_submit_an_end_for_all_archers(): void
    {
        [$owner,$event,$group] = $this->ownedEvent();
        $group->update(['name'=>'反曲公開組','arrow_count'=>12,'arrows_per_end'=>6]);
        $members = User::factory()->count(2)->create();
        $registrations = $members->map(fn ($member) => $this->registration($event,$group,$member));
        $registrations->each->update(['status'=>'checked_in', 'checked_in_at'=>now(), 'checked_in_by'=>$owner->id]);
        $secondGroup = EventGroup::factory()->create(['event_id'=>$event->id, 'name'=>'複合弓公開組', 'arrow_count'=>12, 'arrows_per_end'=>6]);
        $secondRegistration = $this->registration($event, $secondGroup, User::factory()->create());
        $emptyGroup = EventGroup::factory()->create(['event_id'=>$event->id, 'name'=>'無選手組']);

        $this->actingAs($owner)->post(route('organizer.events.scoring.store',$event), [
            'name'=>'上午資格賽', 'athletes_per_target'=>2, 'confirm_unreported'=>1,
        ])->assertSessionHas('success');

        $this->assertNotNull($event->fresh()->reg_end);
        $this->assertTrue($event->fresh()->registrationStatus() === 'closed');
        $this->assertNotNull($group->fresh()->reg_end);
        $this->assertNotNull($secondGroup->fresh()->reg_end);
        $this->assertNotNull($emptyGroup->fresh()->reg_end);
        $this->assertSame(2, EventScoringSession::where('event_id', $event->id)->count());
        $this->assertDatabaseHas('event_scoring_assignments', ['event_registration_id'=>$secondRegistration->id]);
        $this->assertDatabaseHas('event_registrations', ['id'=>$secondRegistration->id, 'status'=>'no_show', 'result_status'=>'dns']);
        $this->assertSame('dns', $secondRegistration->scoringAssignment->target->status);
        $this->assertSame(0, EventScoringSession::where('event_group_id', $emptyGroup->id)->count());

        $this->actingAs($owner)->post(route('organizer.events.scoring.store',$event), [
            'name'=>'重複排靶', 'athletes_per_target'=>2,
        ])->assertSessionHas('error', '此賽事已完成排靶，不能重複執行。');
        $this->assertSame(1, EventScoringSession::where('event_group_id', $group->id)->count());

        $session = EventScoringSession::where('event_group_id', $group->id)->with('targets.assignments')->firstOrFail();
        $this->assertNotNull($session->event_phase_id);
        $this->assertSame('qualification', $session->phase->type);
        $target = $session->targets->first();
        $this->assertCount(2,$target->assignments);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $target->device_pin);

        $this->get(route('scoring-stations.show',$target->access_token))
            ->assertOk()
            ->assertSee('驗證並綁定此設備')
            ->assertDontSee($members[0]->name);

        $this->post(route('scoring-stations.claim',$target->access_token), ['pin'=>'000000'])
            ->assertSessionHasErrors('pin');
        $this->assertNull($target->fresh()->device_token_hash);

        $deviceToken = 'test-scoring-device-token';
        $target->update([
            'device_token_hash'=>hash('sha256', $deviceToken),
            'device_bound_at'=>now(),
        ]);
        $cookieName = 'scoring_device_'.$target->id;

        $this->get(route('scoring-stations.show',$target->access_token))
            ->assertStatus(423)
            ->assertSee('不允許第二台設備讀取或輸入成績');

        $this->withCookie($cookieName, $deviceToken)
            ->get(route('scoring-stations.show',$target->access_token))
            ->assertOk()
            ->assertSee('靶號')
            ->assertSee('總覽')
            ->assertSee('計分')
            ->assertSee('送出')
            ->assertSee($members[0]->name)
            ->assertSee($members[1]->name);

        $this->withCookie($cookieName, $deviceToken)
            ->post(route('scoring-stations.ends.store',$target->access_token), [
            'end_number'=>1,
            'scores'=>[
                $registrations[0]->id=>['X','10','9','8','7','6'],
                $registrations[1]->id=>['10','10','9','9','8','8'],
            ],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('event_score_entries', [
            'event_registration_id'=>$registrations[0]->id, 'end_number'=>1, 'end_total'=>50,
        ]);
        $this->assertSame(1,$target->fresh()->last_completed_end);
        $this->assertSame('in_progress', $session->phase->fresh()->status);

        $oldAccessToken = $target->access_token;
        $this->actingAs($owner)
            ->delete(route('organizer.events.scoring.targets.device.destroy', [$event, $target]))
            ->assertSessionHas('success');
        $this->assertNull($target->fresh()->device_token_hash);
        $this->assertNotSame($oldAccessToken, $target->fresh()->access_token);
        $this->get(route('scoring-stations.show', $oldAccessToken))->assertNotFound();
    }

    private function eventPayload(): array
    {
        return ['name'=>'測試公開賽','start_date'=>now()->addMonth()->toDateString(),'end_date'=>now()->addMonth()->addDay()->toDateString(),'mode'=>'outdoor','organizer'=>'測試主辦方','reg_start'=>now()->toDateTimeString(),'reg_end'=>now()->addWeeks(2)->toDateTimeString()];
    }

    private function ownedEvent(): array
    {
        $owner=User::factory()->create(); $event=Event::factory()->create(); $group=EventGroup::factory()->create(['event_id'=>$event->id]);
        EventStaff::create(['event_id'=>$event->id,'user_id'=>$owner->id,'role'=>'owner','status'=>'active','invited_by'=>$owner->id]);
        return [$owner,$event,$group];
    }

    private function registration(Event $event, EventGroup $group, User $member): EventRegistration
    {
        return EventRegistration::create(['event_id'=>$event->id,'event_group_id'=>$group->id,'user_id'=>$member->id,'name'=>$member->name,'email'=>$member->email,'status'=>'registered']);
    }

    private function createScoringTarget(Event $event, EventGroup $group, User $owner): void
    {
        $phase = $group->qualificationPhase()->firstOrFail();
        $session = EventScoringSession::create([
            'event_id'=>$event->id,
            'event_group_id'=>$group->id,
            'event_phase_id'=>$phase->id,
            'name'=>$group->name.' 資格賽',
            'total_arrows'=>$group->arrow_count ?: 36,
            'arrows_per_end'=>$group->arrows_per_end ?: 6,
            'athletes_per_target'=>4,
            'status'=>'completed',
            'created_by'=>$owner->id,
        ]);
        $session->targets()->create([
            'target_number'=>1,
            'access_token'=>(string) \Illuminate\Support\Str::uuid(),
            'device_pin'=>'123456',
            'status'=>'completed',
        ]);
    }
}
